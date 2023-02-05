<?php
/**
 *  * Created by mtils on 05.02.2023 at 07:42.
 **/

namespace Koansu\Tests\Core;

use Koansu\Core\None;
use Koansu\Core\PointInTime;
use Koansu\Tests\TestCase;

class PointInTimeTest extends TestCase
{
    /**
     * @test
     */
    public function year_property()
    {
        $unit = $this->time('2016-05-31 12:32:14');

        $this->assertEquals(2016, $unit->year);

        $unit->year = 2014;

        $this->assertEquals(2014, $unit->year);
    }

    /**
     * @test
     */
    public function month_property()
    {
        $unit = $this->time('2016-05-15 12:32:14');

        $this->assertEquals(5, $unit->month);

        $unit->month = 6;

        $this->assertEquals(6, $unit->month);
    }

    /**
     * @test
     */
    public function precision()
    {
        $time = $this->time();
        $this->assertEquals(PointInTime::SECOND, $time->precision());
        $this->assertSame($time, $time->setPrecision(PointInTime::DAY));
        $this->assertEquals(PointInTime::DAY, $time->precision());
    }

    public function test_invalidate()
    {
        $this->assertTrue($this->time()->isValid());
        $this->assertFalse((new PointInTime(new None))->isValid());
    }

    /**
     * @param null $date
     *
     * @return PointInTime
     */
    protected function time($date=null) : PointInTime
    {
        return $date ? PointInTime::createFromFormat('Y-m-d H:i:s', $date) : new PointInTime();
    }
}