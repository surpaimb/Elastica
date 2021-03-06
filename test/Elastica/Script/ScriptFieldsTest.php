<?php
namespace Elastica\Test;

use Elastica\Document;
use Elastica\Query;
use Elastica\Script\Script;
use Elastica\Script\ScriptFields;
use Elastica\Test\Base as BaseTest;
use Elastica\Type\Mapping;

class ScriptFieldsTest extends BaseTest
{
    /**
     * @group unit
     */
    public function testNewScriptFields()
    {
        $script = new Script('1 + 2');

        // addScript
        $scriptFields = new ScriptFields();
        $scriptFields->addScript('test', $script);
        $this->assertSame($scriptFields->getParam('test'), $script);

        // setScripts
        $scriptFields = new ScriptFields();
        $scriptFields->setScripts([
            'test' => $script,
        ]);
        $this->assertSame($scriptFields->getParam('test'), $script);

        // Constructor
        $scriptFields = new ScriptFields([
            'test' => $script,
        ]);
        $this->assertSame($scriptFields->getParam('test'), $script);
    }

    /**
     * @group unit
     */
    public function testSetScriptFields()
    {
        $query = new Query();
        $script = new Script('1 + 2');

        $scriptFields = new ScriptFields([
            'test' => $script,
        ]);
        $query->setScriptFields($scriptFields);
        $this->assertSame($query->getParam('script_fields'), $scriptFields);

        $query->setScriptFields([
            'test' => $script,
        ]);
        $this->assertSame($query->getParam('script_fields')->getParam('test'), $script);
    }

    /**
     * @group unit
     * @expectedException \Elastica\Exception\InvalidException
     */
    public function testNameException()
    {
        $script = new Script('1 + 2');
        $scriptFields = new ScriptFields([$script]);
    }

    /**
     * @group functional
     */
    public function testQuery()
    {
        $index = $this->_createIndex();

        $type = $index->getType('test');

        $doc = new Document(1, ['firstname' => 'guschti', 'lastname' => 'ruflin']);
        $type->addDocument($doc);
        $index->refresh();

        $query = new Query();
        $script = new Script('1 + 2');
        $scriptFields = new ScriptFields([
            'test' => $script,
        ]);
        $query->setScriptFields($scriptFields);

        $resultSet = $type->search($query);
        $first = $resultSet->current()->getData();

        // 1 + 2
        $this->assertEquals(3, $first['test'][0]);
    }

    /**
     * @group functional
     */
    public function testScriptFieldWithJoin()
    {
        $client = $this->_getClient();
        $index = $client->getIndex('testscriptfieldwithjoin');
        $index->create([], true);
        $type = $index->getType('test');

        $mapping = new Mapping();
        $mapping->setType($type);

        $mapping = new Mapping($type, [
            'text' => ['type' => 'keyword'],
            'name' => ['type' => 'keyword'],
            'my_join_field' => [
                'type' => 'join',
                'relations' => [
                    'question' => 'answer',
                ],
            ],
        ]);

        $type->setMapping($mapping);
        $index->refresh();

        $doc1 = new Document(1, [
            'text' => 'this is the 1st question',
            'my_join_field' => [
                'name' => 'question',
            ],
        ], 'test');

        $doc2 = new Document(2, [
            'text' => 'this is the 2nd question',
            'my_join_field' => [
                'name' => 'question',
            ],
        ], 'test');

        $index->addDocuments([$doc1, $doc2]);

        $doc3 = new Document(3, [
            'text' => 'this is an answer, the 1st',
            'name' => 'rico',
            'my_join_field' => [
                'name' => 'answer',
                'parent' => 1,
            ],
        ], 'test', 'testparentid');

        $doc4 = new Document(4, [
            'text' => 'this is an answer, the 2nd',
            'name' => 'fede',
            'my_join_field' => [
                'name' => 'answer',
                'parent' => 2,
            ],
        ], 'test', 'testparentid');

        $doc5 = new Document(5, [
            'text' => 'this is an answer, the 3rd',
            'name' => 'fede',
            'my_join_field' => [
                'name' => 'answer',
                'parent' => 2,
            ],
        ], 'test', 'testparentid');

        $this->_getClient()->addDocuments([$doc3, $doc4, $doc5], ['routing' => 1]);
        $index->refresh();

        $query = new Query();
        $script = new Script("doc['my_join_field#question']");
        $scriptFields = new ScriptFields([
            'text' => $script,
        ]);
        $query->setScriptFields($scriptFields);
        $resultSet = $index->search($query);
        $results = $resultSet->getResults();

        $this->assertEquals(1, ($results[0]->getHit())['fields']['text'][0]);
        $this->assertEquals(2, ($results[1]->getHit())['fields']['text'][0]);
    }
}
