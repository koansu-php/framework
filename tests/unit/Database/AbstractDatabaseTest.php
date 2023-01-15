<?php
/**
 *  * Created by mtils on 14.01.2023 at 21:23.
 **/

namespace Koansu\Tests\Database;

use DateTime;
use DateTimeInterface;
use Koansu\Core\Str;
use Koansu\Core\Url;
use Koansu\Database\Contracts\DatabaseConnection;
use Koansu\Database\Factories\SQLiteFactory;
use Koansu\SQL\Query;
use Koansu\SQL\QueryRenderer;
use Koansu\SQL\SQL;
use Koansu\Tests\TestCase;
use Koansu\Tests\TestData;
use Koansu\Text\Streams\CSVReadStream;

use function array_keys;
use function crc32;
use function explode;
use function file_get_contents;
use function in_array;
use function range;
use function str_split;

abstract class AbstractDatabaseTest extends TestCase
{
    use TestData;

    /**
     * @var DatabaseConnection
     */
    protected static $con;

    /**
     * @var array
     */
    protected static $data = [];

    /**
     * @var string[][]
     */
    protected static $providerToGroups = [
        'hotmail.com'   => ['Moderator'],
        'gmail.com'     => ['Support'],
        'yahoo.com'     => ['Moderator','Support'],
        'other'         => ['Administrator']
    ];

    /**
     * @var DateTime
     */
    protected static $created_at;

    /**
     * @var DateTime
     */
    protected static $updated_at;

    /**
     * @beforeClass
     */
    public static function createDatabase() : void
    {
        static::$con = (new SQLiteFactory())->__invoke(new Url('sqlite://memory'));
        static::createSchema(static::$con);
        static::$created_at = (new DateTime())->modify('-1 day');
        static::$updated_at = new DateTime();
        static::loadData(static::dataFile('sample-contacts-500.csv'));
        static::fillDatabase(static::$con, static::$data);
    }

    /**
     * @afterClass
     */
    public static function destroyDatabase() : void
    {
        static::$con->close();
    }

    protected static function createSchema(DatabaseConnection $con) : void
    {
        $schemaDir = static::dirOfTests('database/schema');

        $tables = [
            'contacts',
            'users',
            'tokens',
            'groups',
            'user_group',
            'project_types',
            'files',
            'projects',
            'project_file'
        ];

        foreach ($tables as $basename) {
            $con->write(file_get_contents("$schemaDir/$basename.sql"));
        }

    }

    protected static function loadData($file) : void
    {
        $reader = new CSVReadStream($file);
        $id = 1;
        foreach ($reader as $row) {
            $row['password'] = crc32($row['web']);
            $row['created_at'] = static::$created_at;
            $row['updated_at'] = static::$updated_at;
            static::$data[$id] = $row;
            $id++;
        }
    }

    /**
     * Fill the database with users, contacts, ...
     *
     * People with hotmail addresses will be in group Moderator
     * People with gmail addresses will be in group Support
     * People with yahoo addresses will be in group Support and Moderator
     * People with other addresses will be in group Administrator
     * People with email starting with s get tokens (crc32 of mail and token_type - the last number of their phone)
     * People with email starting with s and ending with com get project for each county word
     * People with email starting with s and ending with com gets the user with id - 100 as its parent
     * Projects have the type of their owners mail provider
     * Projects get a file for every digit of contacts address start
     *
     * @param DatabaseConnection $con
     * @param array $data
     */
    protected static function fillDatabase(DatabaseConnection $con, array $data) : void
    {

        $providers = array_keys(static::$providerToGroups);

        $groupTemplate = [
            ['name'    => 'Administrator'],
            ['name'    => 'Moderator'],
            ['name'    => 'Support'],
        ];

        $groupIds = [];
        foreach($groupTemplate as $item) {
            $item['created_at'] = new DateTime();
            $item['updated_at'] = new DateTime();
            $groupId = (int)$con->query('groups')->insert($item, true);
            $groupIds[$item['name']] = $groupId;
        }

        $projectTypeIds = [];
        foreach($providers as $provider) {
            $projectTypeId = $con->query('project_types')->insert([
                                                                      'name'          => $provider,
                                                                      'created_at'    => new DateTime(),
                                                                      'updated_at'    => new DateTime(),
                                                                  ]);
            $projectTypeIds[$provider] = $projectTypeId;
        }

        foreach($data as $i=>$row) {

            $nextUserId = isset($userId) ? $userId + 1 : 1;

            $contactData = static::only(
                ['first_name', 'last_name', 'address', 'company', 'city', 'county', 'postal', 'phone1', 'phone2', 'created_at', 'updated_at'],
                $row
            );

            $contactId = $con->query('contacts')->insert($contactData, true);

            $userData = static::only(
                ['email', 'password', 'web', 'created_at', 'updated_at'],
                $row
            );

            $userData['contact_id'] = $contactId;

            if ($nextUserId > 100) {
                $userData['parent_id'] = $nextUserId-100;
            }

            $userId = $con->query('users')->insert($userData, true);

            $mailProvider = self::mailProvider($userData['email']);

            foreach(static::groupNames($userData['email']) as $groupName) {
                $con->query('user_group')->insert([
                                                      'user_id'   =>  $userId,
                                                      'group_id'  =>  $groupIds[$groupName]
                                                  ]);
            }

            if (Str::stringStartsWith($userData['email'], 's')) {
                $tokenTypes = range(1, (int)substr($contactData['phone1'], -1));

                foreach ($tokenTypes as $tokenType) {
                    $token = crc32($userData['email'] . '-' . $tokenType);
                    $con->query('tokens')->insert([
                                                      'user_id'       => $userId,
                                                      'token_type'    => $tokenType,
                                                      'token'         => $token,
                                                      'expires_at'    => (new DateTime())->modify('+14 days'),
                                                      'created_at'    => new DateTime(),
                                                      'updated_at'    => new DateTime(),
                                                  ]);
                }
            }

            if (!Str::stringStartsWith($userData['email'], 's') && Str::stringEndsWith($userData['email'], 'com')) {
                continue;
            }

            $countyWords = explode(' ', $contactData['county']);

            foreach ($countyWords as $word) {
                $projectId = $con->query('projects')->insert([
                                                                 'name'          => $word,
                                                                 'type_id'       => $projectTypeIds[$mailProvider],
                                                                 'owner_id'      => $userId,
                                                                 'created_at'    => new DateTime(),
                                                                 'updated_at'    => new DateTime(),
                                                             ], true);

                $digits = str_split(explode(' ', $row['address'])[0]);

                foreach($digits as $j=>$digit) {
                    $fileName = "project_file_{$projectId}_$digit-$j.jpg";
                    $fileId = $con->query('files')->insert([
                                                               'name'  => $fileName,
                                                               'created_at'    => new DateTime(),
                                                               'updated_at'    => new DateTime(),
                                                           ], true);
                    $con->query('project_file')->insert([
                                                            'project_id'    => $projectId,
                                                            'file_id'       => $fileId
                                                        ], false);
                }

            }
        }
    }

    /**
     * @param string $email
     *
     * @return string[]
     */
    protected static function groupNames(string $email) : array
    {
        $mailProvider = self::mailProvider($email);
        return static::$providerToGroups[$mailProvider] ?? [];
    }

    /**
     * @param string $email
     * @return string
     */
    protected static function mailProvider(string $email) : string
    {
        $providers = array_keys(static::$providerToGroups);
        $mailHost = explode('@', $email)[1];
        return in_array($mailHost, $providers) ? $mailHost : 'other';
    }

    protected static function only(array $keys, array $data) : array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $data[$key] ?? null;
        }
        return $result;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected static function datesToStrings(array $data) : array
    {
        $format = static::$con->dialect()->timestampFormat();
        $casted = [];
        foreach ($data as $key=>$value) {
            $casted[$key] = $value instanceof DateTimeInterface ? $value->format($format) : $value;
        }
        return $casted;
    }

    /**
     * Dump a query for debug purposes.
     *
     * @param Query $query
     * @param bool $echo (default: true)
     *
     * @return string
     */
    protected function dumpQuery(Query $query, bool $echo=true) : string
    {
        $exp = (new QueryRenderer())->renderSelect($query);
        $string = SQL::render($exp->__toString(), $exp->getBindings());
        if ($echo) {
            echo "\n$string";
        }
        return $string;
    }
}