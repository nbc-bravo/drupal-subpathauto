<?php

namespace Drupal\Tests\subpathauto\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\simpletest\UserCreationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * @coversDefaultClass \Drupal\subpathauto\PathProcessor
 * @group subpathauto
 */
class SubPathautoKernelTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'subpathauto', 'node', 'user'];

  /**
   * @var \Drupal\Core\Path\AliasStorage
   */
  protected $aliasStorage;

  /**
   * The service under testing.
   *
   * @var \Drupal\subpathauto\PathProcessor
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    // Create the node bundles required for testing.
    $type = NodeType::create([
      'type' => 'page',
      'name' => 'page',
    ]);
    $type->save();

    $this->aliasStorage = $this->container->get('path.alias_storage');
    $this->sut = $this->container->get('path_processor_subpathauto');
  }

  /**
   * @covers ::processInbound
   */
  public function testProcessInbound() {
    Node::create(['type' => 'page', 'title' => 'test'])->save();
    $this->aliasStorage->save('/node/1', '/kittens');

    // Alias should not be converted for aliases that are not valid.
    $processed = $this->sut->processInbound('/kittens/are-fake', Request::create('kittens/are-fake'));
    $this->assertEquals('/kittens/are-fake', $processed);

    // Alias should be converted even when the user doesn't have permissions to
    // view the page.
    $processed = $this->sut->processInbound('/kittens/edit', Request::create('kittens/edit'));
    $this->assertEquals('/node/1/edit', $processed);

    // Alias should be converted because of admin user has access to edit the
    // node.
    $admin_user = $this->createUser();
    \Drupal::currentUser()->setAccount($admin_user);
    $processed = $this->sut->processInbound('/kittens/edit', Request::create('kittens/edit'));
    $this->assertEquals('/node/1/edit', $processed);
  }

}
