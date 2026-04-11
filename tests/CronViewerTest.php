<?php
declare(strict_types=1);

namespace GDC\Tests;

final class CronViewerTest extends WordPressTestCase {
	public function testGetCronEventsReturnsEmptyArrayWhenNoCrons(): void {
		$GLOBALS['gdc_test_state']['cron_array'] = [];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertSame([], $events);
	}

	public function testGetCronEventsParsesScheduledEvents(): void {
		$ts = time() + 3600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'wp_scheduled_delete' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => 'daily',
						'interval' => 86400,
						'args'     => [],
					],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertCount(1, $events);
		$this->assertSame('wp_scheduled_delete', $events[0]['hook']);
		$this->assertSame('daily', $events[0]['schedule']);
		$this->assertSame(86400, $events[0]['interval']);
		$this->assertFalse($events[0]['overdue']);
	}

	public function testGetCronEventsDetectsOverdueEvents(): void {
		$ts = time() - 7200;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'my_hook' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => '',
						'interval' => 0,
						'args'     => [],
					],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertTrue($events[0]['overdue']);
	}

	public function testGetCronEventsSortsByTimestampAscending(): void {
		$now = time();
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$now + 7200 => [
				'late_hook' => [
					'40cd750bba9870f18aada2478b24840a' => ['schedule' => '', 'interval' => 0, 'args' => []],
				],
			],
			$now + 1800 => [
				'early_hook' => [
					'40cd750bba9870f18aada2478b24840a' => ['schedule' => '', 'interval' => 0, 'args' => []],
				],
			],
		];

		$events = \GDC_Cron_Viewer::get_cron_events();

		$this->assertSame('early_hook', $events[0]['hook']);
		$this->assertSame('late_hook', $events[1]['hook']);
	}

	public function testIntervalLabelConvertsSeconds(): void {
		$this->assertSame('30s', \GDC_Cron_Viewer::interval_label(30));
		$this->assertSame('5m', \GDC_Cron_Viewer::interval_label(300));
		$this->assertSame('2h', \GDC_Cron_Viewer::interval_label(7200));
		$this->assertSame('1d', \GDC_Cron_Viewer::interval_label(86400));
	}

	public function testHumanTimeDiffReturnsFutureForFutureTs(): void {
		$result = \GDC_Cron_Viewer::human_time_diff(time() + 3600);

		$this->assertStringContainsString('in', $result);
	}

	public function testHumanTimeDiffReturnsPastForPastTs(): void {
		$result = \GDC_Cron_Viewer::human_time_diff(time() - 3600);

		$this->assertStringContainsString('ago', $result);
	}

	public function testRenderOutputsTableWhenEventsExist(): void {
		$ts = time() + 600;
		$GLOBALS['gdc_test_state']['cron_array'] = [
			$ts => [
				'my_cron_hook' => [
					'40cd750bba9870f18aada2478b24840a' => [
						'schedule' => 'hourly',
						'interval' => 3600,
						'args'     => [],
					],
				],
			],
		];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('my_cron_hook', $html);
		$this->assertStringContainsString('hourly', $html);
		$this->assertStringContainsString('gdc-cron-table', $html);
	}

	public function testRenderOutputsEmptyStateWhenNoCrons(): void {
		$GLOBALS['gdc_test_state']['cron_array'] = [];

		ob_start();
		\GDC_Cron_Viewer::render();
		$html = ob_get_clean();

		$this->assertStringContainsString('No scheduled cron events', $html);
	}

	public function testGetInlineStylesReturnsNonEmptyString(): void {
		$this->assertNotEmpty(\GDC_Cron_Viewer::get_inline_styles());
	}
}
