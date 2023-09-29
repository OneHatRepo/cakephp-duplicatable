<?php
declare(strict_types=1);

namespace TestApp\Model\Table;

use Cake\ORM\Behavior\Translate\EavStrategy;
use Cake\ORM\Table;

class InvoicesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->addBehavior('Translate', [
            'fields' => ['name'],
            'strategyClass' => EavStrategy::class,
        ]);

        $this->addBehavior('Duplicatable.Duplicatable', [
            'contain' => [
                'InvoiceData',
                'InvoiceItems.InvoiceItemProperties',
                'InvoiceItems.InvoiceItemVariations',
                'InvoiceTypes',
                'Tags',
            ],
            'remove' => [
                'created',
                'items.created',
            ],
            'set' => [
                'copied' => true,
            ],
            'prepend' => [
                'items.invoice_item_properties.name' => 'NEW ',
            ],
            'append' => [
                'name' => ' - copy',
                'invoice_data.data' => ' - copy',
            ],
            'preserveJoinData' => false,
        ]);

        $this->hasOne('InvoiceData');

        $this->belongsTo('InvoiceTypes');
        $this->belongsToMany('Tags', [
            'joinTable' => 'invoices_tags',
            'through' => 'InvoicesTags',
        ]);

        $this->hasMany('InvoiceItems', [
            'className' => 'TestApp\Model\Table\InvoiceItemsTable',
            'propertyName' => 'items',
        ]);
    }
}
