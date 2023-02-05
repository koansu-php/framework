<?php
/**
 *  * Created by mtils on 04.02.2023 at 20:57.
 **/

namespace Koansu\Tests\Validation;

use ArrayIterator;
use DateTime;
use InvalidArgumentException;
use Koansu\Core\DataStructures\ByTypeContainer;
use Koansu\Core\Exceptions\ImplementationException;
use Koansu\Core\None;
use Koansu\Core\PointInTime;
use Koansu\Validation\Exceptions\ConstraintViolationException;
use Koansu\Expression\Constraint;
use Koansu\Expression\ConstraintGroup;
use Koansu\Tests\TestCase;
use Koansu\Testing\LoggingCallable;
use Koansu\Validation\Matcher;
use stdClass;
use UnderflowException;


class MatcherTest extends TestCase
{

    public function test___call_with_own_methods()
    {
        $matcher = $this->newMatcher();

        $this->assertTrue($matcher->supports('equals'));

        $this->assertTrue($matcher->equals(2, 2));
        $this->assertFalse($matcher->not_equal(2, 2));
        $this->assertFalse($matcher->equals(2, 3));
    }

    /**
     * @test
     */
    public function test___call_throws_exception_if_no_arguments_passed()
    {
        $matcher = $this->newMatcher();
        $this->expectException(UnderflowException::class);
        $matcher->equals();
    }

    /**
     * @test
     */
    public function test___call_throws_exception_if_constraint_not_supported()
    {
        $matcher = $this->newMatcher();
        $this->expectException(ImplementationException::class);
        $matcher->foo('bar');
    }

    public function test___call_calls_extension_with_resource()
    {
        $resource = new stdClass();

        $isResource = new LoggingCallable(function ($value, $really, $res) use (&$resource) {
            return $res === $resource;
        });

        $matcher = $this->newMatcher();
        $matcher->extend('is_resource', $isResource);



        $this->assertTrue($matcher->match('foo', 'is_resource:1', $resource));

        $this->assertCount(1, $isResource);
        $this->assertEquals('foo', $isResource->arg(0));
        $this->assertEquals('1', $isResource->arg(1));
        $this->assertSame($resource, $isResource->arg(2));


        $this->assertGreaterThan(1, $matcher->names());
        $this->assertTrue($matcher->supports('is_resource'));
    }

    public function test_check_with_single_string_rule()
    {
        $matcher = $this->newMatcher();

        $this->assertTrue($matcher->match(2, 'equals:2'));
        $this->assertFalse($matcher->match(2, 'equals:3'));
    }

    public function test_check_with_multiple_string_rules()
    {
        $matcher = $this->newMatcher();

        $this->assertTrue($matcher->match(2, 'required|min:2|max:4'));
        $this->assertTrue($matcher->match(3, 'required|min:2|max:4'));
        $this->assertFalse($matcher->match(5, 'required|min:2|max:4'));
        $this->assertFalse($matcher->match(5, 'required|min:6|max:9'));

    }

    public function test_check_with_single_array_rule()
    {
        $matcher = $this->newMatcher();

        $this->assertTrue($matcher->match(2, ['equals' => 2]));
        $this->assertFalse($matcher->match(2, ['equals' => 3]));
    }

    public function test_check_with_multiple_array_rules()
    {
        $matcher = $this->newMatcher();

        $this->assertTrue($matcher->match(2, ['min' => 2, 'max' => 15]));
        $this->assertFalse($matcher->match(2, ['min' => 3, 'max' => 15]));
    }

    public function test_check_with_Constraint()
    {
        $matcher = $this->newMatcher();
        $c = new Constraint('equals', [3]);
        $this->assertTrue($matcher->match(3, $c));
        $this->assertFalse($matcher->match(4, $c));

        // without operator fuzzy check
        $c = new Constraint('min', [3]);
        $this->assertTrue($matcher->match(3, $c));
        $this->assertTrue($matcher->match([1,2,3], $c));

        // with operator strict check
        $c = new Constraint('min', [3], '>=');
        $this->assertTrue($matcher->match(3, $c));
        $this->assertFalse($matcher->match([1,2,3], $c));

    }

    public function test_check_with_ConstraintGroup()
    {
        $matcher = $this->newMatcher();

        $group = new ConstraintGroup();

        $group->add(new Constraint('required'));
        $group->add(new Constraint('min', [3]));
        $group->add(new Constraint('max', [9]));

        $this->assertTrue($matcher->match(3, $group));
        $this->assertFalse($matcher->match(10, $group));
    }

    /**
     * @throws \Ems\Contracts\Core\Errors\ConstraintFailure
     */
    public function test_force_with_single_string_rule()
    {
        $matcher = $this->newMatcher();

        $this->assertTrue($matcher->force(2, 'equals:2'));

        try {
            $matcher->force(3, 'equals:22');
            $this->fail('Matcher must throw an ConstraintValidationException');
        } catch (ConstraintViolationException $e) {

        }
    }

    public function test_required()
    {
        $this->assertFails(null, 'required');
        $this->assertFails('', 'required');
        $this->assertFails('    ', 'required');
        $this->assertFails([], 'required');
        $this->assertPasses([1], 'required');
    }

    public function test_min()
    {
        $this->assertPasses(3, 'min:3');
        $this->assertPasses([1,2,3], 'min:3');
        $this->assertPasses('haha', 'min:3');
        $this->assertPasses(new DateTime('2017-12-12'), 'min:2017-12-12');
        $this->assertPasses(3, 'min:2');
        $this->assertFails(3, 'min:4');
        $this->assertFails([1,2,3], 'min:4');
        $this->assertFails('hah', 'min:4');
        $this->assertFails(new DateTime('2017-12-10'), 'min:2017-12-11');
    }

    public function test_max()
    {
        $this->assertPasses(3, 'max:3');
        $this->assertPasses([1,2,3], 'max:3');
        $this->assertPasses('haha', 'max:4');
        $this->assertPasses(new DateTime('2017-12-12'), 'max:2017-12-12');
        $this->assertPasses(3, 'max:3');
        $this->assertFails(3, 'max:2');
        $this->assertFails([1,2,3], 'max:2');
        $this->assertFails('hah', 'max:2');
        $this->assertFails(new DateTime('2017-12-10'), 'max:2017-12-09');
    }

    public function test_greater()
    {
        $this->assertPasses(3, 'greater:2');
        $this->assertPasses([1,2,3], 'greater:2');
        $this->assertPasses('haha', 'greater:2');
        $this->assertPasses(new DateTime('2017-12-12'), 'greater:2017-12-11');
        $this->assertPasses(3, 'greater:1');
        $this->assertFails(3, 'greater:3');
        $this->assertFails([1,2,3], 'greater:3');
        $this->assertFails('hah', 'greater:3');
        $this->assertFails(new DateTime('2017-12-10'), 'greater:2017-12-11');
        $this->assertFails(new DateTime('2017-12-10'), 'greater:foo');
    }

    public function test_less()
    {
        $this->assertPasses(1, 'less:2');
        $this->assertPasses([1,2,3], 'less:4');
        $this->assertPasses('haha', 'less:5');
        $this->assertPasses(new DateTime('2017-12-12'), 'less:2017-12-13');
        $this->assertPasses(0, 'less:1');
        $this->assertFails(3, 'less:3');
        $this->assertFails([1,2,3], 'less:3');
        $this->assertFails('hah', 'less:3');
        $this->assertFails(new DateTime('2017-12-14'), 'less:2017-12-13');
    }

    public function test_between()
    {
        $this->assertPasses(1, 'between:0,2');
        $this->assertPasses(1, 'between:0,1');
        $this->assertFails(2, 'between:0,1');
    }

    public function test_size()
    {
        $this->assertPasses(1, 'size:1');
        $this->assertFails(2, 'size:1');
        $this->assertPasses([1,2], 'size:2');
        $this->assertFails([1], 'size:2');
        $this->assertPasses('ab', 'size:2');
        $this->assertFails('a', 'size:2');
    }

    public function test_compare()
    {
        $matcher = $this->newMatcher();
        $this->assertPasses('2017-07-01', 'compare:=,2017-07-01');
        $this->assertTrue($matcher->compare('foo', '<>', 'bar'));
        $this->assertTrue($matcher->compare('foo', '!=', 'bar'));
        $this->assertFalse($matcher->compare('foo', '!=', 'foo'));

        $a = new stdClass();
        $b = new stdClass();
        $null = null;
        $this->assertTrue($matcher->compare($a, 'is not', $b));
        $this->assertFalse($matcher->compare($a, 'is', $b));
        $this->assertTrue($matcher->compare($a, 'is', $a));
        $this->assertFalse($matcher->compare($a, 'is not', $a));
        $this->assertTrue($matcher->is($a, $a));
        $this->assertFalse($matcher->is($a, $b));
        $this->assertFalse($matcher->is_not($a, $a));

    }

    /**
     * @test
     */
    public function test_compare_throws_exception_with_unknown_operator()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->newMatcher()->compare(1, 'doo', 2);
    }

    public function test_after()
    {
        $this->assertPasses('2017-07-01', 'after:2017-06-30');
        $this->assertFails('2017-07-01', 'after:2017-07-01');
        $this->assertFails('foo', 'after:2017-07-01');

        $this->assertPasses('05/2017/31', 'after:2017-04-01,m/Y/d');
        $this->assertPasses('05/2017/31', 'after:04/2017/01,m/Y/d');

        $this->assertFails('05/2017/31', 'after:2017-08-01,m/Y/d');
    }

    public function test_before()
    {
        $this->assertPasses('2017-07-01', 'before:2017-07-02');
        $this->assertFails('2017-07-01', 'before:2017-07-01');
        $this->assertFails('foo', 'before:2017-07-01');

        $this->assertPasses('05/2017/31', 'before:2017-09-01,m/Y/d');
        $this->assertPasses('05/2017/31', 'before:09/2017/01,m/Y/d');

        $this->assertFails('05/2017/31', 'before:2017-04-01,m/Y/d');
    }

    public function test_type()
    {
        $this->assertPasses('2017-07-01', 'type:string');
        $this->assertPasses([], 'type:array');
    }

    public function test_int()
    {
        $this->assertPasses(322, 'int');
        $this->assertPasses('15', 'int');
        $this->assertFails('15.4', 'int');
        $this->assertFails(15.4, 'int');
    }

    public function test_bool()
    {
        $this->assertPasses(true, 'bool');
        $this->assertPasses(1, 'bool');
        $this->assertFails('15.4', 'bool');
        $this->assertFails([], 'bool');
    }

    public function test_numeric()
    {
        $this->assertPasses(15, 'numeric');
        $this->assertPasses(15.4, 'numeric');
        $this->assertPasses('15.4', 'numeric');
        $this->assertFails('15.4ff', 'numeric');

    }

    public function test_string()
    {
        $this->assertPasses('', 'string');
    }

    public function test_checkTrue()
    {
        $this->assertPasses(true, 'true');
    }

    public function test_checkFalse()
    {
        $this->assertPasses('', 'false');
    }

    public function test_checkIn()
    {
        $this->assertPasses('1', 'in:1,2,4');
        $this->assertPasses('2', 'in:1,2,4');
        $this->assertFails('3', 'in:1,2,4');
        $this->assertPasses('4', 'in:1,2,4');

        $matcher = $this->newMatcher();
        $this->assertTrue($matcher->in(1, ['1', '2']));
        $this->assertTrue($matcher->in(1, '1', '2'));

        $this->assertFalse($matcher->in(3, ['1', '2']));
        $this->assertFalse($matcher->in(3, '1', '2'));
    }

    public function test_checkNotIn()
    {
        $this->assertFails('1', 'not_in:1,2,4');
        $this->assertFails('2', 'not_in:1,2,4');
        $this->assertPasses('3', 'not_in:1,2,4');
        $this->assertFails('4', 'not_in:1,2,4');

        $matcher = $this->newMatcher();
        $this->assertFalse($matcher->notIn(1, ['1', '2']));
        $this->assertFalse($matcher->notIn(1, '1', '2'));

        $this->assertTrue($matcher->notIn(3, ['1', '2']));
        $this->assertTrue($matcher->notIn(3, '1', '2'));
    }

    public function test_checkDate()
    {
        $matcher = $this->newMatcher();

        $this->assertTrue($matcher->date(new PointInTime()));
        $this->assertFalse($matcher->date(new PointInTime(new None())));

        $this->assertTrue($matcher->date(new MatcherTestDate()));
        $this->assertFalse($matcher->date(new MatcherTestDate(null)));

        $this->assertTrue($matcher->date(new DateTime()));
        $this->assertTrue($matcher->date('2015-01-01'));

        $this->assertFalse($matcher->date(new stdClass()));

        $this->assertFalse($matcher->date('foo'));
        $this->assertFalse($matcher->date('2015-01-32'));

        $this->assertTrue($matcher->date('2017-12-12 13:15:43'));

        $this->assertFalse($matcher->date('2017-12-12 25:15:43'));
        $this->assertFalse($matcher->date('2017-12-12 23:85:43'));
        $this->assertFalse($matcher->date('2017-12-12 23:15:92'));

    }

    public function test_checkDatetime()
    {
        $matcher = $this->newMatcher();

        $this->assertTrue($matcher->dateTime(new PointInTime()));
        $this->assertFalse($matcher->dateTime(new PointInTime(new None())));
        $time = new PointInTime();
        $time->precision = PointInTime::DAY;
        $this->assertFalse($matcher->dateTime($time));
        $time->precision = PointInTime::HOUR;
        $this->assertTrue($matcher->dateTime($time));

        $this->assertFalse($matcher->dateTime('2021-01-31'));
        $this->assertTrue($matcher->dateTime('2021-01-31 08:00'));

        $this->assertFalse($matcher->dateTime('2021-01-31 09 21'));
        $this->assertTrue($matcher->dateTime('2021-01-31 09 21', 'Y-m-d H i'));
        $this->assertFalse($matcher->dateTime('2021-01-31 09 21', 'Y-m-d'));
    }

    public function test_checkTime()
    {
        $matcher = $this->newMatcher();
        $this->assertTrue($matcher->dateTime(new PointInTime()));
        $this->assertFalse($matcher->dateTime(new PointInTime(new None())));
        $time = new PointInTime();
        $time->precision = PointInTime::DAY;
        $this->assertFalse($matcher->dateTime($time));
        $time->precision = PointInTime::HOUR;
        $this->assertTrue($matcher->dateTime($time));

        $this->assertTrue($matcher->time(new DateTime()));

        $this->assertTrue($matcher->time('00:24'));
        $this->assertTrue($matcher->time('00:24:32'));
        $this->assertFalse($matcher->time('26:24:32'));
        $this->assertFalse($matcher->time('4'));
        $this->assertFalse($matcher->time('04:76'));
        $this->assertFalse($matcher->time('04:23:84'));
    }

    public function test_email()
    {
        $this->assertPasses('me@somewhere.com', 'email');
        $this->assertFails('me-somewhere.com', 'email');
    }

    public function test_url()
    {
        $this->assertPasses('http://somewhere.com', 'url');
        $this->assertFails('980978', 'url');
    }

    public function test_ip()
    {
        $this->assertPasses('192.168.0.4', 'ip');
        $this->assertFails('192.168.0.4.5', 'ip');
        $this->assertFails('192.168.0.4.5', 'ip');
        $this->assertPasses('2001:0db8:85a3:08d3:1319:8a2e:0370:7344', 'ip');
        $this->assertFails('2001:0db8:85a3:08d3:1319:8a2e:0370:7344:', 'ip');

        $this->assertFails('192.168.0.4', 'ipv6');
        $this->assertPasses('2001:0db8:85a3:08d3:1319:8a2e:0370:7344', 'ipv6');
        $this->assertPasses('192.168.0.4', 'ipv4');
        $this->assertFails('2001:0db8:85a3:08d3:1319:8a2e:0370:7344', 'ipv4');
    }

    public function test_digits()
    {
        $this->assertPasses('045123', 'digits:6');
        $this->assertPasses(45123, 'digits:5');
        $this->assertFails('0123', 'digits:5');
        $this->assertFails(new stdClass(), 'digits:5');

    }

    public function test_json()
    {
        $this->assertPasses('[2,3,4]', 'json');
        $this->assertFails('[2,3,4],', 'json');
        $this->assertFails(43, 'json');
    }

    public function test_xml()
    {
        $this->assertPasses('<items><first></first></items>', 'xml');
        $this->assertFails('[2,3,4],', 'xml');
        $this->assertFails(43, 'xml');
    }

    public function test_html()
    {
        $this->assertPasses('<p>Hello</p>', 'html');
        $this->assertFails('[2,3,4],', 'html');
        $this->assertFails(43, 'html');
    }

    public function test_plain()
    {
        $this->assertFails('<p>Hello</p>', 'plain');
        $this->assertPasses('[2,3,4],', 'plain');
        $this->assertFails(new stdClass(), 'plain');
        $this->assertPasses(43, 'plain');
    }

    public function test_tags()
    {
        $this->assertPasses('<p>Hello</p>', 'tags:p');
        $this->assertPasses('<p>Hello <strong>look</strong> at <a href="https://foo.org">this</a></p>', 'tags:p,strong,a');
        $this->assertFails('<p>Hello <strong>look</strong> at <a href="https://foo.org">this</a></p>', 'tags:p,strong');
        $this->assertPasses(43, 'tags:p');
        $this->assertFails(new stdClass(), 'tags:p');
    }

    public function test_chars()
    {
        $this->assertPasses('abcd', 'chars:4');
        $this->assertPasses('', 'chars:0');
        $this->assertPasses(23, 'chars:2');
        $this->assertFails(new stdClass(), 'chars:2');
    }

    public function test_words()
    {
        $this->assertPasses('this is a test', 'words:4');
        $this->assertFails('this is a', 'words:4');
        $this->assertPasses('this is a te4st', 'words:4');
        $this->assertPasses('this is 4 a test', 'words:5');
        $this->assertPasses('Hello', 'words:1');
        $this->assertPasses('Hello, Bye.', 'words:2');
        $this->assertPasses('Dü bist än liäbä Büä', 'words:5');
        $this->assertFails(22, 'words:2');

    }

    public function test_starts_with()
    {
        $this->assertPasses('Hello my dear', 'starts_with:Hello');
        $this->assertFails('Hello my dear', 'starts_with:my');
        $this->assertFails(new stdClass(), 'starts_with:my');
    }

    public function test_ends_with()
    {
        $this->assertPasses('Hello my dear', 'ends_with:dear');
        $this->assertFails('Hello my dear', 'ends_with:a');
        $this->assertFails(new stdClass(), 'starts_with:my');
    }

    public function test_contains()
    {
        $this->assertPasses('Hello my dear', 'contains:dear');
        $this->assertPasses('Hello my dear', 'contains:Hello');
        $this->assertPasses('Hello my dear', 'contains:my');
        $this->assertPasses('Hello my dear', 'contains: ');
        $this->assertFails('Hello my dear', 'contains:rudolph');
        $this->assertPasses([2,4,13], 'contains:4');
        $this->assertFails([2,4,13], 'contains:43');
        $this->assertFails(new ArrayIterator([2,4,13]), 'contains:43');
        $this->assertPasses(new ArrayIterator([2,4,13]), 'contains:13');

    }

    public function test_like()
    {
        $this->assertFails('Hello my dear', 'like:dear');
        $this->assertPasses('Hello my dear', 'like:%dear%');
        $this->assertPasses('Hello my dear', 'like:%dear');
        $this->assertPasses('Hello my dear', 'like:hello%');
        $this->assertPasses('Hello my dear', 'like:%hello%');
        $this->assertFails('Hello my dear', 'like:hello');
        $this->assertPasses('Hello my%', 'like:%my\\%');
        $this->assertFails('Hello my%', 'like:%my');
        $this->assertPasses('Hello my dear', 'like:Hello m_ dear');
        $this->assertPasses('Hello my _dear', 'like:Hello m_ \_dear');
        $this->assertFails('Hello my _dear', 'like:Hello m_ dear');
        $this->assertFails(new stdClass(), 'like:Hello m_ dear');
    }

    public function test_regex()
    {
        $this->assertPasses('Hello my dear', 'regex:/^[a-zA-Z ]+$/');
        $this->assertFails('Hello my dear', 'regex:/^[a-zA-Z]+$/');
        $this->assertFails(new stdClass(), 'regex:/^[a-zA-Z]+$/');
    }

    public function test_alpha()
    {
        $this->assertPasses('alpha', 'alpha');
        $this->assertPasses('alphä', 'alpha');
        $this->assertFails('alph-a', 'alpha');
        $this->assertFails('alph2', 'alpha');
    }

    public function test_alpha_dash()
    {
        $this->assertPasses('alpha', 'alpha_dash');
        $this->assertPasses('al-p_hä', 'alpha_dash');
        $this->assertPasses('alph-a', 'alpha_dash');
        $this->assertFails('alph-2:-a', 'alpha_dash');
    }

    public function test_alpha_num()
    {
        $this->assertPasses('alpha', 'alpha_num');
        $this->assertPasses('alphä13', 'alpha_num');
        $this->assertFails('alph-a', 'alpha_num');
        $this->assertPasses('alph2', 'alpha_num');
    }

    public function test_length()
    {
        $this->assertPasses('124','length:3');
        $this->assertPasses('','length:0');
        $this->assertFails('124','length:2');
        $this->assertFails('124','length:4');
        $this->assertPasses(['a','b','c'],'length:3');
        $this->assertFails(['a','b','c'],'length:2');
        $this->assertFails(['a','b','c'],'length:4');

        $this->assertPasses('1245','length:2-5');
        $this->assertFails('3','length:2-5');
        $this->assertFails('1234567','length:2-5');
    }

    /**
     * @test
     */
    public function array_if_passed()
    {
        $this->assertPasses([], 'array');
        $this->assertFails('Hello', 'array');
        $this->assertPasses(new ByTypeContainer(), 'array');
        $this->assertFails(new stdClass(), 'array');
    }

    protected function newMatcher() : Matcher
    {
        return new Matcher();
    }

    protected function assertPasses($value, $rule, $msg='')
    {
        $this->assertTrue($this->match($value, $rule), $msg);
    }

    protected function assertFails($value, $rule, $msg='')
    {
        $this->assertFalse($this->match($value, $rule), $msg);
    }

    protected function match($value, $rule)
    {
        return $this->newMatcher()->match($value, $rule);
    }
}

class MatcherTestDate
{
    protected $ts = 0;

    public function __construct($ts=0)
    {
        $this->ts = $ts;
    }

    public function getTimestamp()
    {
        return $this->ts;
    }
}