<?php

declare(strict_types=1);

namespace Drupal\Tests\localgov_topics\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for LocalGovDrupal Topics permissions.
 *
 * @group localgov_topics
 */
class TopicPermissionsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'localgov_topics',
    'localgov_roles',
    'taxonomy',
    'user',
  ];

  /**
   * A test topic term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $testTerm;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Create a test topic term.
    $this->testTerm = Term::create([
      'vid' => 'localgov_topic',
      'name' => 'Test Topic',
      'description' => 'A test topic for permissions testing',
    ]);
    $this->testTerm->save();
  }

  /**
   * Test that anonymous users cannot create, edit, or delete topic terms.
   */
  public function testAnonymousUserCannotManageTopics(): void {
    // Anonymous users cannot access the topics overview page.
    $this->drupalGet('admin/structure/taxonomy/manage/localgov_topic/overview');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Anonymous users cannot create topics.
    $this->drupalGet('admin/structure/taxonomy/manage/localgov_topic/add');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Anonymous users cannot edit topics.
    $this->drupalGet('taxonomy/term/' . $this->testTerm->id() . '/edit');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Anonymous users cannot delete topics.
    $this->drupalGet('taxonomy/term/' . $this->testTerm->id() . '/delete');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);
  }

  /**
   * Test that authenticated users cannot create, edit, or delete topic terms.
   */
  public function testAuthenticatedUserCannotManageTopics(): void {
    $authenticatedUser = $this->createUser();
    $this->drupalLogin($authenticatedUser);

    // Authenticated users cannot access the topics overview page.
    $this->drupalGet('admin/structure/taxonomy/manage/localgov_topic/overview');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Authenticated users cannot create topics.
    $this->drupalGet('admin/structure/taxonomy/manage/localgov_topic/add');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Authenticated users cannot edit topics.
    $this->drupalGet('taxonomy/term/' . $this->testTerm->id() . '/edit');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Authenticated users cannot delete topics.
    $this->drupalGet('taxonomy/term/' . $this->testTerm->id() . '/delete');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    $this->drupalLogout();
  }

  /**
   * Test that users with contributor role cannot manage topic terms.
   */
  public function testContributorCannotManageTopics(): void {
    $contributorUser = $this->createUser();
    $contributorUser->addRole('localgov_contributor');
    $contributorUser->save();
    $this->drupalLogin($contributorUser);

    // Contributors cannot access the topics overview page.
    $this->drupalGet('admin/structure/taxonomy/manage/localgov_topic/overview');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Contributors cannot create topics.
    $this->drupalGet('admin/structure/taxonomy/manage/localgov_topic/add');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Contributors cannot edit topics.
    $this->drupalGet('taxonomy/term/' . $this->testTerm->id() . '/edit');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    // Contributors cannot delete topics.
    $this->drupalGet('taxonomy/term/' . $this->testTerm->id() . '/delete');
    $this->assertSession()->statusCodeEquals(Response::HTTP_FORBIDDEN);

    $this->drupalLogout();
  }

  /**
   * Test that editors can create, edit, and delete topic terms.
   */
  public function testEditorCanManageTopics(): void {
    $editorUser = $this->createUser([
      'access administration pages',
      'access taxonomy overview',
    ]);
    $editorUser->addRole('localgov_editor');
    $editorUser->save();
    $this->drupalLogin($editorUser);

    // Editors can access the topics overview page.
    $this->drupalGet('admin/structure/taxonomy/manage/localgov_topic/overview');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);

    // Editors can access the create topic form.
    $this->drupalGet('admin/structure/taxonomy/manage/localgov_topic/add');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->fieldExists('name[0][value]');

    // Editors can create topics.
    $this->submitForm([
      'name[0][value]' => 'New Topic by Editor',
      'description[0][value]' => 'Description for new topic',
    ], 'Save');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextContains('Created new term New Topic by Editor');

    // Editors can access the edit form.
    $this->drupalGet('taxonomy/term/' . $this->testTerm->id() . '/edit');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->fieldExists('name[0][value]');

    // Editors can edit topics.
    $this->submitForm([
      'name[0][value]' => 'Updated Topic by Editor',
    ], 'Save');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextContains('Updated term Updated Topic by Editor');

    // Editors can access the delete form.
    $this->drupalGet('taxonomy/term/' . $this->testTerm->id() . '/delete');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextContains('Are you sure you want to delete the taxonomy term Updated Topic by Editor');

    // Editors can delete topics.
    $this->submitForm([], 'Delete');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextContains('Deleted term Updated Topic by Editor');

    $this->drupalLogout();
  }

  /**
   * Test that the correct permissions are assigned to the editor role.
   */
  public function testEditorRoleHasTopicPermissions(): void {
    // Install the localgov_roles module which should grant permissions.
    $editor = Role::load('localgov_editor');

    $this->assertNotEmpty($editor, 'Editor role exists');
    $this->assertTrue($editor->hasPermission('create terms in localgov_topic'), 'Editor can create topics');
    $this->assertTrue($editor->hasPermission('edit terms in localgov_topic'), 'Editor can edit topics');
    $this->assertTrue($editor->hasPermission('delete terms in localgov_topic'), 'Editor can delete topics');
  }

  /**
   * Test that other roles do not have topic permissions.
   */
  public function testOtherRolesDoNotHaveTopicPermissions(): void {
    // Check anonymous role.
    $anonymous = Role::load(RoleInterface::ANONYMOUS_ID);
    $this->assertFalse($anonymous->hasPermission('create terms in localgov_topic'), 'Anonymous cannot create topics');
    $this->assertFalse($anonymous->hasPermission('edit terms in localgov_topic'), 'Anonymous cannot edit topics');
    $this->assertFalse($anonymous->hasPermission('delete terms in localgov_topic'), 'Anonymous cannot delete topics');

    // Check authenticated role.
    $authenticated = Role::load(RoleInterface::AUTHENTICATED_ID);
    $this->assertFalse($authenticated->hasPermission('create terms in localgov_topic'), 'Authenticated cannot create topics');
    $this->assertFalse($authenticated->hasPermission('edit terms in localgov_topic'), 'Authenticated cannot edit topics');
    $this->assertFalse($authenticated->hasPermission('delete terms in localgov_topic'), 'Authenticated cannot delete topics');

    // Check author role (if it exists).
    $author = Role::load('localgov_author');
    if ($author) {
      $this->assertFalse($author->hasPermission('create terms in localgov_topic'), 'Author cannot create topics');
      $this->assertFalse($author->hasPermission('edit terms in localgov_topic'), 'Author cannot edit topics');
      $this->assertFalse($author->hasPermission('delete terms in localgov_topic'), 'Author cannot delete topics');
    }
  }

}
