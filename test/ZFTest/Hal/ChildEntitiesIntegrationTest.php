<?php

/**
 * @see       https://github.com/laminas-api-tools/api-tools-hal for the canonical source repository
 * @copyright https://github.com/laminas-api-tools/api-tools-hal/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas-api-tools/api-tools-hal/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\ApiTools\Hal;

use Laminas\ApiTools\ApiProblem\View\ApiProblemRenderer;
use Laminas\ApiTools\Hal\Collection;
use Laminas\ApiTools\Hal\Entity;
use Laminas\ApiTools\Hal\Link\Link;
use Laminas\ApiTools\Hal\Plugin\Hal as HalHelper;
use Laminas\ApiTools\Hal\View\HalJsonModel;
use Laminas\ApiTools\Hal\View\HalJsonRenderer;
use Laminas\Http\Request;
use Laminas\Mvc\Controller\PluginManager as ControllerPluginManager;
use Laminas\Mvc\Router\Http\TreeRouteStack;
use Laminas\View\Helper\ServerUrl as ServerUrlHelper;
use Laminas\View\Helper\Url as UrlHelper;
use Laminas\View\HelperPluginManager;
use PHPUnit_Framework_TestCase as TestCase;

/**
 * @subpackage UnitTest
 */
class ChildEntitiesIntegrationTest extends TestCase
{
    public function setUp()
    {
        $this->setupRouter();
        $this->setupHelpers();
        $this->setupRenderer();
    }

    public function setupHelpers()
    {
        if (!$this->router) {
            $this->setupRouter();
        }

        $urlHelper = new UrlHelper();
        $urlHelper->setRouter($this->router);

        $serverUrlHelper = new ServerUrlHelper();
        $serverUrlHelper->setScheme('http');
        $serverUrlHelper->setHost('localhost.localdomain');

        $linksHelper = new HalHelper();
        $linksHelper->setUrlHelper($urlHelper);
        $linksHelper->setServerUrlHelper($serverUrlHelper);

        $this->helpers = $helpers = new HelperPluginManager();
        $helpers->setService('url', $urlHelper);
        $helpers->setService('serverUrl', $serverUrlHelper);
        $helpers->setService('hal', $linksHelper);

        $this->plugins = $plugins = new ControllerPluginManager();
        $plugins->setService('hal', $linksHelper);
    }

    public function setupRenderer()
    {
        if (!$this->helpers) {
            $this->setupHelpers();
        }
        $this->renderer = $renderer = new HalJsonRenderer(new ApiProblemRenderer());
        $renderer->setHelperPluginManager($this->helpers);
    }

    public function setupRouter()
    {
        $routes = array(
            'parent' => array(
                'type' => 'Segment',
                'options' => array(
                    'route' => '/api/parent[/:parent]',
                    'defaults' => array(
                        'controller' => 'Api\ParentController',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'child' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route' => '/child[/:child]',
                            'defaults' => array(
                                'controller' => 'Api\ChildController',
                            ),
                        ),
                    ),
                ),
            ),
        );
        $this->router = $router = new TreeRouteStack();
        $router->addRoutes($routes);
    }

    public function setUpParentEntity()
    {
        $this->parent = (object) array(
            'id'   => 'anakin',
            'name' => 'Anakin Skywalker',
        );
        $entity = new Entity($this->parent, 'anakin');

        $link = new Link('self');
        $link->setRoute('parent');
        $link->setRouteParams(array('parent'=> 'anakin'));
        $entity->getLinks()->add($link);

        return $entity;
    }

    public function setUpChildEntity($id, $name)
    {
        $this->child = (object) array(
            'id'   => $id,
            'name' => $name,
        );
        $entity = new Entity($this->child, $id);

        $link = new Link('self');
        $link->setRoute('parent/child');
        $link->setRouteParams(array('child'=> $id));
        $entity->getLinks()->add($link);

        return $entity;
    }

    public function setUpChildCollection()
    {
        $children = array(
            array('luke', 'Luke Skywalker'),
            array('leia', 'Leia Organa'),
        );
        $this->collection = array();
        foreach ($children as $info) {
            $collection[] = call_user_func_array(array($this, 'setUpChildEntity'), $info);
        }
        $collection = new Collection($this->collection);
        $collection->setCollectionRoute('parent/child');
        $collection->setEntityRoute('parent/child');
        $collection->setPage(1);
        $collection->setPageSize(10);
        $collection->setCollectionName('child');

        $link = new Link('self');
        $link->setRoute('parent/child');
        $collection->getLinks()->add($link);

        return $collection;
    }

    public function testParentEntityRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Laminas\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertEquals('parent', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $parent = $this->setUpParentEntity();
        $model  = new HalJsonModel();
        $model->setPayload($parent);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin', $test->_links->self->href);
    }

    public function testChildEntityRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Laminas\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertEquals('luke', $matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $child = $this->setUpChildEntity('luke', 'Luke Skywalker');
        $model = new HalJsonModel();
        $model->setPayload($child);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child/luke', $test->_links->self->href);
    }

    public function testChildCollectionRendersAsExpected()
    {
        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Laminas\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('parent'));
        $this->assertNull($matches->getParam('child'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $collection = $this->setUpChildCollection();
        $model = new HalJsonModel();
        $model->setPayload($collection);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->_links->self->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertObjectHasAttribute('child', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->child);

        foreach ($test->_embedded->child as $child) {
            $this->assertObjectHasAttribute('_links', $child);
            $this->assertObjectHasAttribute('self', $child->_links);
            $this->assertObjectHasAttribute('href', $child->_links->self);
            $this->assertRegex('#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#', $child->_links->self->href);
        }
    }

    public function setUpAlternateRouter()
    {
        $routes = array(
            'parent' => array(
                'type' => 'Segment',
                'options' => array(
                    'route' => '/api/parent[/:id]',
                    'defaults' => array(
                        'controller' => 'Api\ParentController',
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'child' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route' => '/child[/:child_id]',
                            'defaults' => array(
                                'controller' => 'Api\ChildController',
                            ),
                        ),
                    ),
                ),
            ),
        );
        $this->router = $router = new TreeRouteStack();
        $router->addRoutes($routes);
        $this->helpers->get('url')->setRouter($router);
    }

    public function testChildEntityObjectIdentifierMapping()
    {
        $this->setUpAlternateRouter();

        $uri = 'http://localhost.localdomain/api/parent/anakin/child/luke';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Laminas\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertEquals('luke', $matches->getParam('child_id'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $child = $this->setUpChildEntity('luke', 'Luke Skywalker');
        $model = new HalJsonModel();
        $model->setPayload($child);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child/luke', $test->_links->self->href);
    }

    public function testChildEntityIdentifierMappingInsideCollection()
    {
        $this->setUpAlternateRouter();

        $uri = 'http://localhost.localdomain/api/parent/anakin/child';
        $request = new Request();
        $request->setUri($uri);
        $matches = $this->router->match($request);
        $this->assertInstanceOf('Laminas\Mvc\Router\RouteMatch', $matches);
        $this->assertEquals('anakin', $matches->getParam('id'));
        $this->assertNull($matches->getParam('child_id'));
        $this->assertEquals('parent/child', $matches->getMatchedRouteName());

        // Emulate url helper factory and inject route matches
        $this->helpers->get('url')->setRouteMatch($matches);

        $collection = $this->setUpChildCollection();
        $model = new HalJsonModel();
        $model->setPayload($collection);

        $json = $this->renderer->render($model);
        $test = json_decode($json);
        $this->assertObjectHasAttribute('_links', $test);
        $this->assertObjectHasAttribute('self', $test->_links);
        $this->assertObjectHasAttribute('href', $test->_links->self);
        $this->assertEquals('http://localhost.localdomain/api/parent/anakin/child', $test->_links->self->href);

        $this->assertObjectHasAttribute('_embedded', $test);
        $this->assertObjectHasAttribute('child', $test->_embedded);
        $this->assertInternalType('array', $test->_embedded->child);

        foreach ($test->_embedded->child as $child) {
            $this->assertObjectHasAttribute('_links', $child);
            $this->assertObjectHasAttribute('self', $child->_links);
            $this->assertObjectHasAttribute('href', $child->_links->self);
            $this->assertRegex('#^http://localhost.localdomain/api/parent/anakin/child/[^/]+$#', $child->_links->self->href);
        }
    }
}