<?php
declare(strict_types=1);

namespace Duplicatable\Test\TestCase\Model\Behavior;

use Cake\Datasource\EntityInterface;
use Cake\TestSuite\TestCase;

/**
 * DuplicatableBehavior Test Case
 *
 * @property \TestApp\Model\Table\InvoicesTable $Invoices
 */
class DuplicatableBehaviorTest extends TestCase
{
    /**
     * Fixtures
     *
     * @var array
     */
    protected array $fixtures = [
        'plugin.Duplicatable.InvoiceTypes',
        'plugin.Duplicatable.Invoices',
        'plugin.Duplicatable.InvoiceData',
        'plugin.Duplicatable.InvoiceItems',
        'plugin.Duplicatable.InvoiceItemProperties',
        'plugin.Duplicatable.InvoiceItemVariations',
        'plugin.Duplicatable.InvoicesTags',
        'plugin.Duplicatable.I18n',
        'plugin.Duplicatable.Tags',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->Invoices = $this->getTableLocator()->get('Invoices', [
            'className' => 'TestApp\Model\Table\InvoicesTable',
        ]);

        $this->Tags = $this->getTableLocator()->get('Tags');
    }

    /**
     * Test duplicating with deeply nested associations
     *
     * @return void
     */
    public function testDuplicate()
    {
        $result = $this->Invoices->duplicate(1);
        $this->assertInstanceOf('Cake\Datasource\EntityInterface', $result);

        $invoice = $this->Invoices->get(
            $result->id,
            contain: [
                'InvoiceData',
                'InvoiceItems.InvoiceItemProperties',
                'InvoiceItems.InvoiceItemVariations',
                'Tags',
            ],
        );

        // entity
        $this->assertEquals('Invoice name - copy', $invoice->name);
        $this->assertEquals('Contact name', $invoice->contact_name);
        $this->assertEquals(1, $invoice->copied);
        $this->assertEquals(null, $invoice->created);

        // has one
        $this->assertEquals(3, $invoice->invoice_data->id);
        $this->assertEquals('Data for invoice 1 - copy', $invoice->invoice_data->data);

        // has many
        $this->assertEquals('Item 1', $invoice->items[0]->name);
        $this->assertEquals(null, $invoice->items[0]->created);
        $this->assertEquals('Item 2', $invoice->items[1]->name);
        $this->assertEquals(null, $invoice->items[1]->created);

        // double has many
        $this->assertEquals('NEW Property 1', $invoice->items[0]->invoice_item_properties[0]->name);
        $this->assertEquals('NEW Property 2', $invoice->items[0]->invoice_item_properties[1]->name);
        $this->assertEquals('NEW Property 3', $invoice->items[1]->invoice_item_properties[0]->name);
        $this->assertEquals('Variation 1', $invoice->items[0]->invoice_item_variations[0]->name);
        $this->assertEquals('Variation 2', $invoice->items[1]->invoice_item_variations[0]->name);
        $this->assertEquals('Variation 3', $invoice->items[1]->invoice_item_variations[1]->name);

        // belongs to
        $this->assertEquals(2, $invoice->invoice_type_id);

        // check that invoice types are not duplicated
        $this->assertEquals(2, $this->Invoices->InvoiceTypes->find()->count());

        // belongs to many
        $this->assertEquals(1, $invoice->tags[0]->id);
        $this->assertEquals('Tag 1', $invoice->tags[0]->name);
        $this->assertEquals(2, $invoice->tags[1]->id);
        $this->assertEquals('Tag 2', $invoice->tags[1]->name);
        $this->assertEquals(null, $invoice->tags[0]->_joinData->is_preserved);
        $this->assertEquals(null, $invoice->tags[1]->_joinData->is_preserved);

        // check that tags are not duplicated
        $this->assertEquals(2, $this->Tags->find()->count());

        // check original entity
        $original = $this->Invoices->get(
            1,
            contain: [
                'InvoiceData',
                'InvoiceItems.InvoiceItemProperties',
                'InvoiceItems.InvoiceItemVariations',
                'Tags',
            ],
        );

        // has many
        $this->assertEquals('Property 1', $original->items[0]->invoice_item_properties[0]->name);
        $this->assertEquals('Property 2', $original->items[0]->invoice_item_properties[1]->name);
        $this->assertEquals('Property 3', $original->items[1]->invoice_item_properties[0]->name);
        $this->assertEquals('Variation 1', $original->items[0]->invoice_item_variations[0]->name);
        $this->assertEquals('Variation 2', $original->items[1]->invoice_item_variations[0]->name);
        $this->assertEquals('Variation 3', $original->items[1]->invoice_item_variations[1]->name);

        // belongs to many
        $this->assertEquals(2, count($original->tags));
    }

    public function testWithTranslation()
    {
        $this->Invoices->removeBehavior('Duplicatable');
        $this->Invoices->addBehavior('Duplicatable.Duplicatable', [
            'finder' => 'translations',
            'contain' => ['InvoiceItems.InvoiceItemProperties'],
            'append' => [
                'name' => ' - copy',
            ],
            'prepend' => [
                'items.invoice_item_properties.name' => 'NEW ',
            ],
        ]);

        $result = $this->Invoices->duplicate(1);

        $invoice = $this->Invoices->find('translations')
            ->where(['id' => $result->id])
            ->contain(['InvoiceItems.InvoiceItemProperties' => function ($q) {
                return $q->find('translations');
            }])
            ->first();

        $this->assertNotEmpty($invoice->_translations);
        $this->assertEquals('Invoice name - es - copy', $invoice->_translations['es']['name']);
        $this->assertEquals(
            'NEW Property 1 - es',
            $invoice->items[0]->invoice_item_properties[0]->_translations['es']['name']
        );

        $I18n = $this->getTableLocator()->get('I18n');
        $records = $I18n->find()
            ->where([
                'locale' => 'es',
                'model' => 'Invoices',
            ])
            ->all();
        $this->assertEquals(2, $records->count());

        $records = $I18n->find()
            ->where([
                'locale' => 'es',
                'model' => 'InvoiceItemProperties',
            ])
            ->all();
        $this->assertEquals(2, $records->count());
    }

    public function testDuplicateWithSetters()
    {
        $this->Invoices->removeBehavior('Duplicatable');
        $this->Invoices->addBehavior('Duplicatable.Duplicatable', [
            'set' => [
                'copied' => true,
                'name' => 'mail',
                'contact_name' => function (EntityInterface $entity) {
                    return strrev($entity->get('contact_name'));
                },
            ],
        ]);

        $result = $this->Invoices->duplicate(1);
        $invoice = $this->Invoices->get($result->id);

        $this->assertEquals('mail', $invoice->name);
        $this->assertEquals('eman tcatnoC', $invoice->contact_name);
        $this->assertEquals(1, $invoice->copied);
    }

    public function testPreserveJoinData()
    {
        $this->Invoices->removeBehavior('Duplicatable');

        $this->Invoices->addBehavior('Duplicatable.Duplicatable', [
            'contain' => [
                'Tags',
            ],
            'preserveJoinData' => true,
        ]);

        $result = $this->Invoices->duplicate(1);
        $invoice = $this->Invoices->get(
            $result->id,
            contain: [
                'Tags',
            ],
        );

        $this->assertEquals(true, $invoice->tags[0]->_joinData->is_preserved);
        $this->assertEquals(true, $invoice->tags[1]->_joinData->is_preserved);

        // check that tags are not duplicated
        $this->assertEquals(2, $this->Invoices->Tags->find()->count());
    }

    /**
     * Test that an entity with optionally null association can have fields removed when being duplicated
     *
     * @return void
     */
    public function testDuplicateWithRemoveOnNullAssociations()
    {
        $this->Invoices->behaviors()->get('Duplicatable')->setConfig('remove', ['invoice_data.data']);

        $result = $this->Invoices->duplicate(3);
        $this->assertInstanceOf(EntityInterface::class, $result);

        $this->assertNotEquals(3, $result->get('id'));
        $this->assertEmpty($result->get('invoice_data'));
    }
}
