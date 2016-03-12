<?php

namespace Askedio\Tests;


class JsonTest extends ApiCase
{
    /** @var string */
    public $baseUrl = 'http://localhost';

    /** @var string */
    private $read = '{"data":{"type":"user","id":50,"attributes":{"id":50,"name":"uyooewew","email":"uy@asd.copm","created_at":"2016-03-10 17:40:46","updated_at":"2016-03-11 20:45:18"}},"jsonapi":{"version":"1.0","self":"v1"}}';

    /** @var string */
    private $list = '{"data":[],"meta":{"total":0,"currentPage":1,"perPage":"10","hasMorePages":false,"hasPages":false},"links":{"self":"http:\/\/localhost\/api\/user?page=1","first":"http:\/\/localhost\/api\/user?page=1","last":"http:\/\/localhost\/api\/user?page=1","next":null,"prev":null},"jsonapi":{"version":"1.0","self":"v1"}}';

  


    public function testRead()
    {
        $this->createUser();
        $this->json('GET', '/api/user/1');
        $response = $this->response;
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(config('jsonapi.content_type'), $response->headers->get('Content-type'));
        $this->seeJsonStructure($this->getKeys($this->read));
    }

    public function testUpdate()
    {
        $this->createUser();
        $this->json('PATCH', '/api/user/1', [
          'name' => 'testupdate',
          ]);
        $response = $this->response;
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(config('jsonapi.content_type'), $response->headers->get('Content-type'));
        $this->seeJson(['name' => 'testupdate']);
    }

    public function testDelete()
    {
        $this->createUser();

        $this->json('DELETE', '/api/user/1');
        $response = $this->response;
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(config('jsonapi.content_type'), $response->headers->get('Content-type'));
        $this->seeJsonStructure($this->getKeys($this->read));
    }

    public function testList()
    {
        $this->json('GET', '/api/user');
        $response = $this->response;
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(config('jsonapi.content_type'), $response->headers->get('Content-type'));
        $this->seeJsonStructure($this->getKeys($this->list));
  }

    private function createUser()
    {
        $this->json('POST', '/api/user', [
          'name'     => 'test',
          'email'    => 'test@test.com',
          'password' => bcrypt('password')]);
    }
}
