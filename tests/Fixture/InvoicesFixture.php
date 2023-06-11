<?php
namespace Duplicatable\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class InvoicesFixture extends TestFixture
{
    public array $records = [
        [
            'invoice_type_id' => 2,
            'name' => 'Invoice name',
            'contact_name' => 'Contact name',
            'copied' => 0,
            'created' => '2015-03-17 01:20:23',
        ],
        [
            'invoice_type_id' => 1,
            'name' => 'Invoice name 2',
            'contact_name' => 'Contact name 2',
            'copied' => 0,
            'created' => '2015-05-17 03:20:54',
        ],
        [
            'invoice_type_id' => 1,
            'name' => 'Invoice with removed optionally null associations',
            'contact_name' => 'Contact name 3',
            'copied' => 0,
            'created' => '2015-05-17 03:20:54',
        ],
    ];
}
