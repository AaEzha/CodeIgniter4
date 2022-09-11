<?php

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Database\Live;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\RawSql;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Tests\Support\Database\Seeds\CITestSeeder;

/**
 * @group DatabaseLive
 *
 * @internal
 */
final class UpdateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = true;
    protected $seed    = CITestSeeder::class;

    public function testUpdateSetsAllWithoutWhere()
    {
        $this->db->table('user')->update(['name' => 'Bobby']);

        $result = $this->db->table('user')->get()->getResult();

        $this->assertSame('Bobby', $result[0]->name);
        $this->assertSame('Bobby', $result[1]->name);
        $this->assertSame('Bobby', $result[2]->name);
        $this->assertSame('Bobby', $result[3]->name);
    }

    public function testUpdateSetsAllWithoutWhereAndLimit()
    {
        try {
            $this->db->table('user')->update(['name' => 'Bobby'], null, 1);

            $result = $this->db->table('user')
                ->orderBy('id', 'asc')
                ->get()
                ->getResult();

            // this is really a bad test - indexes and other things can affect sort order
            if ($this->db->DBDriver === 'SQLSRV') {
                $this->assertSame('Derek Jones', $result[0]->name);
                $this->assertSame('Bobby', $result[1]->name);
            } else {
                $this->assertSame('Bobby', $result[0]->name);
                $this->assertSame('Ahmadinejad', $result[1]->name);
            }

            $this->assertSame('Richard A Causey', $result[2]->name);
            $this->assertSame('Chris Martin', $result[3]->name);
        } catch (DatabaseException $e) {
            // This DB doesn't support Where and Limit together
            // but we don't want it called a "Risky" test.
            $this->assertTrue(true);
        }
    }

    public function testUpdateWithWhere()
    {
        $this->db->table('user')->update(['name' => 'Bobby'], ['country' => 'US']);

        $result = $this->db->table('user')->get()->getResultArray();

        $rows = [];

        foreach ($result as $row) {
            if ($row['name'] === 'Bobby') {
                $rows[] = $row;
            }
        }

        $this->assertCount(2, $rows);
    }

    public function testUpdateWithWhereAndLimit()
    {
        try {
            $this->db->table('user')->update(['name' => 'Bobby'], ['country' => 'US'], 1);

            $result = $this->db->table('user')->get()->getResult();

            $this->assertSame('Bobby', $result[0]->name);
            $this->assertSame('Ahmadinejad', $result[1]->name);
            $this->assertSame('Richard A Causey', $result[2]->name);
            $this->assertSame('Chris Martin', $result[3]->name);
        } catch (DatabaseException $e) {
            // This DB doesn't support Where and Limit together
            // but we don't want it called a "Risky" test.
            $this->assertTrue(true);
        }
    }

    public function testUpdateBatch()
    {
        $data = [
            [
                'name'    => 'Derek Jones',
                'country' => 'Greece',
            ],
            [
                'name'    => 'Ahmadinejad',
                'country' => 'Greece',
            ],
        ];

        $this->db->table('user')->updateBatch($data, 'name');

        $this->seeInDatabase('user', [
            'name'    => 'Derek Jones',
            'country' => 'Greece',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Ahmadinejad',
            'country' => 'Greece',
        ]);
    }

    public function testUpdateWithWhereSameColumn()
    {
        $this->db->table('user')->update(['country' => 'CA'], ['country' => 'US']);

        $result = $this->db->table('user')->get()->getResultArray();

        $rows = [];

        foreach ($result as $row) {
            if ($row['country'] === 'CA') {
                $rows[] = $row;
            }
        }

        $this->assertCount(2, $rows);
    }

    public function testUpdateWithWhereSameColumn2()
    {
        // calling order: set() -> where()
        $this->db->table('user')
            ->set('country', 'CA')
            ->where('country', 'US')
            ->update();

        $result = $this->db->table('user')->get()->getResultArray();

        $rows = [];

        foreach ($result as $row) {
            if ($row['country'] === 'CA') {
                $rows[] = $row;
            }
        }

        $this->assertCount(2, $rows);
    }

    public function testUpdateWithWhereSameColumn3()
    {
        // calling order: where() -> set() in update()
        $this->db->table('user')
            ->where('country', 'US')
            ->update(['country' => 'CA']);

        $result = $this->db->table('user')->get()->getResultArray();

        $rows = [];

        foreach ($result as $row) {
            if ($row['country'] === 'CA') {
                $rows[] = $row;
            }
        }

        $this->assertCount(2, $rows);
    }

    /**
     * @group single
     *
     * @see   https://github.com/codeigniter4/CodeIgniter4/issues/324
     */
    public function testUpdatePeriods()
    {
        $this->db->table('misc')
            ->where('key', 'spaces and tabs')
            ->update([
                'value' => '30.192',
            ]);

        $this->seeInDatabase('misc', [
            'value' => '30.192',
        ]);
    }

    /**
     * @see https://codeigniter4.github.io/CodeIgniter4/database/query_builder.html#updating-data
     */
    public function testSetWithoutEscape()
    {
        $this->db->table('job')
            ->set('description', $this->db->escapeIdentifiers('name'), false)
            ->update();

        $this->seeInDatabase('job', [
            'name'        => 'Developer',
            'description' => 'Developer',
        ]);
    }

    public function testSetWithBoolean()
    {
        $this->db->table('type_test')
            ->set('type_boolean', false)
            ->update();

        $this->seeInDatabase('type_test', [
            'type_boolean' => false,
        ]);

        $this->db->table('type_test')
            ->set('type_boolean', true)
            ->update();

        $this->seeInDatabase('type_test', [
            'type_boolean' => true,
        ]);
    }

    public function testUpdateBatchTwoConstraints()
    {
        $data = [
            [
                'id'      => 1,
                'name'    => 'Derek Jones Changes',
                'country' => 'US',
            ],
            [
                'id'      => 2,
                'name'    => 'Ahmadinejad Does Not Change',
                'country' => 'Greece',
            ],
        ];

        $this->db->table('user')->updateBatch($data, 'id, country');

        $this->seeInDatabase('user', [
            'name'    => 'Derek Jones Changes',
            'country' => 'US',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Ahmadinejad',
            'country' => 'Iran',
        ]);
    }

    public function testUpdateBatchConstraintsRawSqlandAlias()
    {
        $data = [
            [
                'id'      => 1,
                'name'    => 'Derek Jones Changes',
                'country' => 'US',
            ],
            [
                'id'      => 2,
                'name'    => 'Ahmadinejad Changes',
                'country' => 'Iraq', // same length but different
            ],
            [
                'id'      => 3,
                'name'    => 'Richard A Causey Changes',
                'country' => 'US',
            ],
            [
                'id'      => 4,
                'name'    => 'Chris Martin Does Not Change',
                'country' => 'Greece', // not same length
            ],
        ];

        $this->db->table('user')->setData($data, true, 'd')->updateBatch(null, ['id', new RawSql('LENGTH(db_user.country) = LENGTH(d.country)')]);

        $this->seeInDatabase('user', [
            'name'    => 'Derek Jones Changes',
            'country' => 'US',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Ahmadinejad Changes',
            'country' => 'Iraq',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Richard A Causey Changes',
            'country' => 'US',
        ]);
        $this->seeInDatabase('user', [
            'name'    => 'Chris Martin',
            'country' => 'UK',
        ]);
    }
}
