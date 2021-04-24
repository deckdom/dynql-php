<?php

namespace Dynql\Tests;

use Dynql\FragmentStore;
use Dynql\UnresolvedFragmentError;

use PHPUnit\Framework\TestCase;

class BasicTests extends TestCase
{
    private $validNames = [
        'objecta',
        'something',
        'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'seperated_name',
        '_test',
        '_a_',
        'fragment',
        'hello_w0r7d',
    ];
    private $invalidNames = [
        '.name',
        ';name',
        '-name',
        '@include',
        '@skip',
        '0test',
        '1test',
        'on',
        'with.dot',
        'aaa...test',
        'utf8_1_Ђ',
        'utf8_2_Ӵ',
        'utf8_3_թ',
        'utf8_4_ࢯ',
        'utf8_5_Ꮆ',
        'utf8_6_Ṹ',
        'utf8_7_ツ',
        'utf8_8_㇎',
        'utf8_9_ꯐ',
        'utf8_10_😀',
        'another.name',
        'weird|.chars',
        'special;chars',
        '-dash',
        'dash-',
        'da-sh',
        ':colon',
        'colon:',
        'col:on',
    ];

    public function testIsValidName()
    {
        $store = new FragmentStore();
        foreach ($this->validNames as $name) {
            $this->assertTrue($store->isValidName($name));
        }
    }

    public function testIsInvalidName()
    {
        $store = new FragmentStore();
        foreach ($this->validNames as $name) {
            $this->assertFalse($store->isValidName($name));
        }
    }

    public function testGetSpreadFragmentNames()
    {
        $store = new FragmentStore();
        $list = [
            [
                'definition' => '{ something { ...test1 } }',
                'names' => ['test1'],
            ],
            [
                'definition' => '{ something { ...test1 ...test2 } }',
                'names' => ['test1', 'test2'],
            ],
            [
                'definition' => '{ something { ...test1;...test2 } }',
                'names' => ['test1', 'test2'],
            ],
            [
                'definition' => '{ something { ...test1...test2 } }',
                'names' => ['test1', 'test2'],
            ],
            [
                'definition' => '{ something { ... test1... test2 } }',
                'names' => ['test1', 'test2'],
            ],
            [
                'definition' => '{ something { ...test_one...test_two } }',
                'names' => ['test_one', 'test_two'],
            ],
            [
                'definition' => '{ something { ...test1one...test_two } }',
                'names' => ['test1one', 'test_two'],
            ],
            [
                'definition' => '{ something { ...test1\n...test2 } }',
                'names' => ['test1', 'test2'],
            ]
        ];
        foreach ($list as $entry) {
            $this->expectEquals($store.getSpreadFragmentNames($entry['definition']), $entry['names']);
        }
    }

    public function testGetDefinedFragmentNamesWithValidNames()
    {
        $store = new FragmentStore();
        foreach ($this->validNames as $name) {
            $definition = "fragment ${name} on Something { field }";
            $this->expectEquals($store->getDefinedFragmentNames($definition), [$name]);
        }
    }

    public function testGetDefinedFragmentNamesWithInvalidNames()
    {
        $store = new FragmentStore();
        foreach ($this->invalidNames as $name) {
            $definition = "fragment ${name} on Something { field }";
            $this->expectEquals($store->getDefinedFragmentNames($definition), []);
        }
    }
}
