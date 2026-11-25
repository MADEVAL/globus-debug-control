<?php
/**
 * Tests for the Overview aggregation and health model.
 *
 * @package GlobusDebugControl
 */

declare(strict_types=1);

namespace GDC\Tests;

use GDC_Overview;

/**
 * Overview data tests.
 */
final class OverviewTest extends WordPressTestCase {

	/**
	 * Healthy baseline fixture.
	 *
	 * @param array<string, mixed> $overrides Overrides.
	 * @return array<string, mixed>
	 */
	private function healthy( array $overrides = array() ): array {
		return array_merge(
			array(
				'options'    => array(
					'enabled'         => 1,
					'log_enabled'     => 1,
					'log_max_size_mb' => 10,
					'suppress_notices' => 1,
				),
				'wp_config'  => array(
					'wp_debug'     => true,
					'wp_debug_log' => true,
				),
				'log'        => array(
					'exists'   => true,
					'writable' => true,
					'size'     => 1024,
				),
				'cron'       => array(
					'orphans'    => 0,
					'stuck_cron' => false,
				),
				'transients' => array( 'expired' => 0 ),
				'autoload'   => array(
					'count'      => 10,
					'total_size' => 1000,
				),
				'http'       => array( 'capturing' => false ),
				'mu'         => array(
					'installed' => true,
					'current'   => true,
				),
			),
			$overrides
		);
	}

	public function testHealthySetupScoresOneHundred(): void {
		$health = GDC_Overview::health( $this->healthy() );

		$this->assertSame( 100, $health['score'] );
		$this->assertSame( 'Secure', $health['label'] );
		$this->assertSame( 'gdc-gauge--secure', $health['color'] );
		$this->assertSame( 0, $health['attention'] );
	}

	public function testDisabledPluginAndMissingDropinLowerTheScore(): void {
		$health = GDC_Overview::health(
			$this->healthy(
				array(
					'options' => array(
						'enabled'     => 0,
						'log_enabled' => 0,
					),
					'mu'      => array(
						'installed' => false,
						'current'   => false,
					),
				)
			)
		);

		$this->assertLessThan( 100, $health['score'] );
		$this->assertGreaterThan( 0, $health['disabled'] );
		$this->assertGreaterThan( 0, $health['attention'] );
	}

	public function testProductionHazardsRaiseAttentionItems(): void {
		$health = GDC_Overview::health(
			$this->healthy(
				array(
					'cron'       => array(
						'orphans'    => 4,
						'stuck_cron' => true,
					),
					'transients' => array( 'expired' => 3 ),
					'http'       => array( 'capturing' => true ),
					'autoload'   => array(
						'count'      => 250,
						'total_size' => 2000000,
					),
				)
			)
		);

		$this->assertGreaterThanOrEqual( 5, $health['attention'] );
		$this->assertLessThan( 90, $health['score'] );
	}

	public function testScoreLabelsMapToThresholds(): void {
		$this->assertSame( 'Secure', GDC_Overview::score_label( 95 ) );
		$this->assertSame( 'Strong', GDC_Overview::score_label( 75 ) );
		$this->assertSame( 'Moderate', GDC_Overview::score_label( 55 ) );
		$this->assertSame( 'Weak', GDC_Overview::score_label( 35 ) );
		$this->assertSame( 'Critical', GDC_Overview::score_label( 10 ) );
	}

	public function testScoreColorsMapToThresholds(): void {
		$this->assertSame( 'gdc-gauge--secure', GDC_Overview::score_color( 95 ) );
		$this->assertSame( 'gdc-gauge--strong', GDC_Overview::score_color( 75 ) );
		$this->assertSame( 'gdc-gauge--moderate', GDC_Overview::score_color( 55 ) );
		$this->assertSame( 'gdc-gauge--weak', GDC_Overview::score_color( 35 ) );
		$this->assertSame( 'gdc-gauge--critical', GDC_Overview::score_color( 10 ) );
	}

	public function testHealthHandlesEmptyData(): void {
		$health = GDC_Overview::health( array() );

		$this->assertIsInt( $health['score'] );
		$this->assertSame( 4, count( $health['groups'] ) );
	}

	public function testCronChartBucketsEventsWithinTwentyFourHours(): void {
		$now = time();

		$GLOBALS['gdc_test_state']['cron_array'] = array(
			$now + 60 => array( 'gdc_test_hook' => array( array( 'args' => array() ) ) ),
			$now + 7200 => array( 'gdc_test_hook' => array( array( 'args' => array() ) ) ),
			$now + 90000 => array( 'gdc_test_hook' => array( array( 'args' => array() ) ) ),
			$now + 100000 => array( 'gdc_test_hook' => array( array( 'args' => array() ) ) ),
		);

		$chart = GDC_Overview::cron_chart();

		$this->assertCount( 8, $chart['labels'] );
		$this->assertCount( 8, $chart['values'] );
		$this->assertSame( 2, array_sum( $chart['values'] ) );
	}

	public function testAutoloadChartLimitsToEightRowsAndTruncatesNames(): void {
		$rows = array();

		for ( $i = 0; $i < 12; $i++ ) {
			$rows[] = array(
				'name'       => 'gdc_option_with_a_very_long_name_number_' . $i,
				'value_size' => ( 12 - $i ) * 1024,
			);
		}

		$chart = GDC_Overview::autoload_chart( $rows );

		$this->assertCount( 8, $chart['labels'] );
		$this->assertSame( 12.0, $chart['values'][0] );
		$this->assertLessThanOrEqual( 28, strlen( $chart['labels'][0] ) );
	}

	public function testGetDataReturnsExpectedKeys(): void {
		$this->refreshOptions( array( 'enabled' => 1 ) );

		$data = GDC_Overview::get_data();

		foreach ( array( 'options', 'log', 'transients', 'autoload', 'cron', 'http', 'mu', 'wp_config', 'health', 'charts' ) as $key ) {
			$this->assertArrayHasKey( $key, $data );
		}
	}

	public function testAjaxToggleSettingSavesAllowedKey(): void {
		$this->refreshOptions( array( 'enabled' => 0, 'log_enabled' => 1 ) );

		$_POST['key']         = 'enabled';
		$_POST['value']       = '1';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			$this->plugin()->ajax_toggle_setting();
		} catch ( \GDC_Test_WpDieException $e ) {
			// Expected JSON exit.
		}

		$response = $this->state( 'json_response' );
		$this->assertTrue( $response['success'] );
		$this->assertSame( 1, $response['data']['value'] );

		$options = $GLOBALS['gdc_test_state']['options']['globus_debug_control_options'];
		$this->assertSame( 1, $options['enabled'] );
		$this->assertSame( 1, $options['log_enabled'], 'Other options must survive the toggle.' );
	}

	public function testAjaxToggleSettingRejectsUnknownKey(): void {
		$_POST['key']         = 'install_mu_dropin';
		$_POST['value']       = '1';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			$this->plugin()->ajax_toggle_setting();
		} catch ( \GDC_Test_WpDieException $e ) {
			// Expected JSON exit.
		}

		$response = $this->state( 'json_response' );
		$this->assertFalse( $response['success'] );
	}

	public function testAjaxToggleSettingRequiresCapability(): void {
		$this->refreshOptions( array( 'enabled' => 0 ) );
		$GLOBALS['gdc_test_state']['current_user_can'] = false;

		$_POST['key']         = 'enabled';
		$_POST['value']       = '1';
		$_POST['_ajax_nonce'] = 'test_nonce';

		try {
			$this->plugin()->ajax_toggle_setting();
		} catch ( \GDC_Test_WpDieException $e ) {
			// Expected JSON exit.
		}

		$response = $this->state( 'json_response' );
		$this->assertFalse( $response['success'] );
	}
}
