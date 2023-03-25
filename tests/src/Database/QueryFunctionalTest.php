<?php /** @noinspection SpellCheckingInspection */

/**
 *  * Created by mtils on 15.01.2023 at 12:09.
 **/

namespace Koansu\Tests\Database;

use DateTime;
use Koansu\Core\Str;
use Koansu\Database\PDOResult;
use Koansu\Database\Query;
use Koansu\Pagination\Paginator;
use Koansu\SQL\SQL;
use TypeError;

use function call_user_func;
use function iterator_to_array;

class QueryFunctionalTest extends AbstractDatabaseTest
{
    /**
     * @var array
     */
    protected static $contactKeys = ['first_name', 'last_name', 'company', 'city', 'county', 'postal', 'phone1', 'phone2', 'created_at', 'updated_at'];

    /**
     * @var array
     */
    protected static $userKeys = ['email', 'password', 'web', 'created_at', 'updated_at'];

    /**
     * @test
     */
    public function it_can_be_instantiated()
    {
        $this->assertInstanceOf(Query::class, static::$con->query());
    }

    /**
     * @test
     */
    public function select_all_entries()
    {

        $count = 0;
        $query = static::$con->query('users');
        $query->join('contacts')->on('users.contact_id', 'contacts.id');

        foreach($query as $row) {
            $data = static::$data[$row['id']];
            $this->assertSameUser($data, $row);
            $this->assertSameContact($data, $row);
            $count++;
        }
        $this->assertEquals(count(static::$data), $count);

    }

    /**
     * @test
     */
    public function select_paginated()
    {

        $perPage = 10;
        $query = static::$con->query('users');
        $query->join('contacts')->on('users.contact_id', 'contacts.id');

        foreach ([1,2,3] as $page) {

            $rows = [];

            $items = $query->orderBy('id')->paginate($page, $perPage);
            $this->assertInstanceOf(Paginator::class, $items);

            foreach($items as $row) {
                $data = static::$data[$row['id']];
                $rows[] = $row;
                $this->assertSameUser($data, $row);
                $this->assertSameContact($data, $row);
            }

            $this->assertEquals(count(static::$data), $items->getTotalCount());
            $this->assertCount($perPage, $rows);

            $first = $items->first();
            $this->assertSameUser(static::$data[$first['id']], $first);
            $this->assertSameContact(static::$data[$first['id']], $first);

            $last = $items->last();
            $this->assertSameUser(static::$data[$last['id']], $last);
            $this->assertSameContact(static::$data[$last['id']], $last);

        }

    }

    /**
     * @test
     */
    public function select_paginated_with_custom_count_query()
    {

        $perPage = 10;
        $query = static::$con->query('users');
        if (!$query instanceof Query) {
            throw new TypeError('The returned query has to be ' . Query::class);
        }

        $countQuery = clone $query;
        $countQuery->offset(null)->limit(null);
        $countQuery->columns = [];
        $countQuery->orderBys = [];
        $countQuery->select(new Str('250 as total'));

        $this->assertSame($query, $query->setCountQuery($countQuery));
        $this->assertSame($countQuery, $query->getCountQuery());


        $query->join('contacts')->on('users.contact_id', 'contacts.id');

        $items = $query->orderBy('id')->paginate(1, $perPage);
        $this->assertInstanceOf(Paginator::class, $items);
        $this->assertSame(250, $items->getTotalCount());

    }

    /**
     * @test
     */
    public function select_paginated_without_paginator()
    {

        $perPage = 10;
        $query = static::$con->query('users');

        if (!$query instanceof Query) {
            throw new TypeError('The returned query has to be ' . Query::class);
        }

        $query->join('contacts')->on('users.contact_id', 'contacts.id');

        Query::$paginatorClassExists = false;
        foreach ([1,2,3] as $page) {

            $rows = [];

            $items = $query->orderBy('id')->paginate($page, $perPage);
            $this->assertInstanceOf(PDOResult::class, $items);

            foreach($items as $row) {
                $data = static::$data[$row['id']];
                $rows[] = $row;
                $this->assertSameUser($data, $row);
                $this->assertSameContact($data, $row);
            }

            $this->assertCount($perPage, $rows);

            $first = $items->first();
            $this->assertSameUser(static::$data[$first['id']], $first);
            $this->assertSameContact(static::$data[$first['id']], $first);

            $last = $items->last();
            $this->assertSameUser(static::$data[$last['id']], $last);
            $this->assertSameContact(static::$data[$last['id']], $last);

        }
        Query::$paginatorClassExists = true;

    }

    /**
     * @test
     */
    public function select_paginated_with_custom_paginator()
    {

        $perPage = 10;
        $query = static::$con->query('users');
        if (!$query instanceof Query) {
            throw new TypeError('The returned query has to be ' . Query::class);
        }
        $query->join('contacts')->on('users.contact_id', 'contacts.id');

        $query->createPaginatorBy(function ($result, $totalCount, $query, $page, $perPage) {
            return [
                'result'        => $result,
                'query'         => $query,
                'page'          => $page,
                'perPage'       => $perPage,
                'totalCount'    => $totalCount
            ];
        });

        foreach ([1,2,3] as $page) {

            $rows = [];

            $result = $query->orderBy('id')->paginate($page, $perPage);
            $items = $result['result'];
            $this->assertInstanceOf(PDOResult::class, $items);
            $this->assertSame($query, $result['query']);
            $this->assertEquals($page, $result['page']);
            $this->assertEquals($perPage, $result['perPage']);

            foreach($items as $row) {
                $data = static::$data[$row['id']];
                $rows[] = $row;
                $this->assertSameUser($data, $row);
                $this->assertSameContact($data, $row);
            }

            $this->assertCount($perPage, $rows);

            $first = $items->first();
            $this->assertSameUser(static::$data[$first['id']], $first);
            $this->assertSameContact(static::$data[$first['id']], $first);

            $last = $items->last();
            $this->assertSameUser(static::$data[$last['id']], $last);
            $this->assertSameContact(static::$data[$last['id']], $last);

        }
        Query::$paginatorClassExists = true;

    }

    /**
     * @test
     */
    public function replace_inserts_or_updates()
    {
        $query = static::$con->query('contacts');
        if (!$query instanceof Query) {
            throw new TypeError('The returned query has to be ' . Query::class);
        }

        $firstName = 'Reed';
        $lastName = 'Weisinger';

        $newLastName = 'Weisinger2';
        $newFirstName = 'Reed2';

        $users = $query->where('last_name', $lastName)
            ->where('first_name', $firstName);

        $found = iterator_to_array($users);
        $this->assertCount(1, $found);
        $this->assertEquals($lastName, $found[0]['last_name']);

        $affected = static::$con->query('contacts')->replace([
                                                                 'id'            => $found[0]['id'],
                                                                 'last_name'     => $newLastName,
                                                                 'first_name'    => $newFirstName,
                                                                 'created_at'    => new DateTime(),
                                                                 'updated_at'    => new DateTime(),
                                                             ]);

        $this->assertSame(1, $affected);

        $users = static::$con->query('contacts')->where('last_name', $lastName)
            ->where('first_name', $firstName);

        if (!$users instanceof Query) {
            throw new TypeError('The returned query has to be ' . Query::class);
        }

        $found = iterator_to_array($users);
        $this->assertCount(0, $found);

        $maxId = static::$con->query('contacts')
            ->select(new Str('MAX(id) AS max_id'))
            ->first()['max_id'];

        $this->assertEquals(500, $maxId);

        $nextId = (int)$maxId+1;

        $affected = static::$con->query('contacts')->replace([
                                                                 'id'            => $nextId,
                                                                 'last_name'     => $lastName,
                                                                 'first_name'    => $firstName,
                                                                 'created_at'    => new DateTime(),
                                                                 'updated_at'    => new DateTime(),
                                                             ]);

        $this->assertSame(1, $affected);

        $users = static::$con->query('contacts')
            ->where('last_name', $lastName)
            ->where('first_name', $firstName);

        $found = iterator_to_array($users);
        $this->assertCount(1, $found);

    }

    /**
     * @test
     */
    public function update_changes_record()
    {
        $user = static::$con->query('contacts')
            ->where('last_name', 'Ear')
            ->where('first_name', 'Luis')
            ->first();

        $this->assertEquals('Luis', $user['first_name']);
        $this->assertEquals('Ear', $user['last_name']);
        $this->assertEquals('Whittington', $user['city']);
        $this->assertEquals('Shropshire', $user['county']);

        $affected = static::$con->query('contacts')
            ->where('id', $user['id'])
            ->update([
                         'city'      => 'Köln',
                         'county'    => 'Nordrhein Westfalen'
                     ]);

        $this->assertSame(1, $affected);

        $user = static::$con->query('contacts')
            ->where('last_name', 'Ear')
            ->where('first_name', 'Luis')
            ->first();

        $this->assertEquals('Luis', $user['first_name']);
        $this->assertEquals('Ear', $user['last_name']);
        $this->assertEquals('Köln', $user['city']);
        $this->assertEquals('Nordrhein Westfalen', $user['county']);

    }

    /**
     * @test
     */
    public function delete_removes_record()
    {
        $lastName = 'Weisinger2';
        $firstName = 'Reed2';


        $user = static::$con->query('contacts')
            ->where('last_name', $lastName)
            ->where('first_name', $firstName)
            ->first();

        $this->assertEquals($lastName, $user['last_name']);

        $affected = static::$con->query('contacts')
            ->where('last_name', $lastName)
            ->where('first_name', $firstName)
            ->delete();

        $this->assertSame(1, $affected);

        $this->assertNull(static::$con->query('contacts')
                              ->where('last_name', $lastName)
                              ->where('first_name', $firstName)
                              ->first());
    }

    /**
     * @test
     */
    public function insert_creates_record()
    {
        $query = static::$con->query('contacts');

        $firstName = 'Michael';
        $lastName = 'Tils';

        $users = $query->where('last_name', $lastName)
            ->where('first_name', $firstName);

        $found = iterator_to_array($users);
        $this->assertCount(0, $found);

        $insertedId = static::$con->query('contacts')->insert(
            [
                'last_name' => $lastName,
                'first_name' => $firstName,
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ]
        );

        $this->assertGreaterThan(500, $insertedId);


        $users = static::$con->query('contacts')
            ->where('last_name', $lastName)
            ->where('first_name', $firstName);

        $found = iterator_to_array($users);
        $this->assertCount(1, $found);

        $this->assertEquals($firstName, $found[0]['first_name']);
        $this->assertEquals($lastName, $found[0]['last_name']);
    }

    /**
     * @test
     */
    public function select_with_unprepared_query()
    {
        $query = static::$con->query('contacts');
        $renderer = $query->getRenderer();

        $proxy = function ($query) use ($renderer) {
            $expression = call_user_func($renderer, $query);
            return SQL::render(
                $expression->__toString(),
                $expression->getBindings()
            );
        };

        $query->setRenderer($proxy);

        $query->where('last_name', 'like', 'C%');

        $count = 0;
        foreach ($query as $row) {
            if ($row['last_name'][0] != 'C') {
                $this->fail('Not matching last name found');
            }
            $count++;
        }

        $this->assertGreaterThan(1, $count);
    }

    /**
     * @test
     */
    public function insert_with_unprepared_query()
    {
        $query = static::$con->query('contacts');

        $firstName = 'Michaela';
        $lastName = 'Tils';

        $users = $query->where('last_name', $lastName)
            ->where('first_name', $firstName);

        $found = iterator_to_array($users);
        $this->assertCount(0, $found);

        $insertQuery = static::$con->query('contacts');
        $renderer = $query->getRenderer();

        $proxy = function ($query) use ($renderer) {
            $expression = call_user_func($renderer, $query);
            return SQL::render($expression->__toString(), $expression->getBindings());
        };

        $insertQuery->setRenderer($proxy);

        $insertedId = $insertQuery->insert(
            [
                'last_name' => $lastName,
                'first_name' => $firstName,
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ]
        );

        $this->assertGreaterThan(500, $insertedId);


        $users = static::$con->query('contacts')
            ->where('last_name', $lastName)
            ->where('first_name', $firstName);

        $found = iterator_to_array($users);
        $this->assertCount(1, $found);

        $this->assertEquals($firstName, $found[0]['first_name']);
        $this->assertEquals($lastName, $found[0]['last_name']);
    }

    protected static function query(string $table=null) : Query
    {
        return static::$con->query($table);
    }

    protected static function assertSameUser($csv, $database)
    {
        $expected = static::datesToStrings(static::only(static::$userKeys, $csv));
        $test = static::only(static::$userKeys, $database);

        static::assertEquals($expected, $test, 'The user data did not match');
    }

    protected static function assertSameContact($csv, $database)
    {
        $expected = static::datesToStrings(static::only(static::$contactKeys, $csv));
        $test = static::only(static::$contactKeys, $database);

        static::assertEquals($expected, $test, 'The contact data did not match');
    }
}