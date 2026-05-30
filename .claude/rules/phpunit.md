# PHPUnit Rules and Conventions

## Test Groups

Every PHPUnit test method MUST declare exactly one `#[Group(...)]` attribute, using one of the four constants defined in `tests/GroupAttributeValidationTest.php`:

| Group constant | Use for |
|----------------|---------|
| `GroupAttributeValidationTest::GROUP_UNIT` | Pure unit tests that exercise a single class or function in isolation, without booting the Symfony kernel and without any database, container, or HTTP client interaction. |
| `GroupAttributeValidationTest::GROUP_INTEGRATION` | Tests that boot the Symfony kernel and exercise services through the DI container, Doctrine, or other infrastructure (most service tests fall here). |
| `GroupAttributeValidationTest::GROUP_FUNCTIONAL` | End-to-end tests that drive the application through the HTTP client (`$this->client->request(...)`) and assert on full request/response, security, or controller behavior. |
| `GroupAttributeValidationTest::GROUP_LINTER` | Static-analysis-style tests that scan the codebase for compliance violations (entity structure, attribute usage, naming conventions, configuration files). |

Enforcement: `tests/GroupAttributeValidationTest::testAllTestMethodsHaveGroupAttributes()` walks the entire `tests/` directory recursively and fails the build if any public `test*` method is missing a `#[Group(...)]` attribute.

Required imports when the test class lives outside the `App\Tests` namespace:

```php
use App\Tests\GroupAttributeValidationTest;
use PHPUnit\Framework\Attributes\Group;
```

Example:

```php
#[Group(GroupAttributeValidationTest::GROUP_INTEGRATION)]
public function testCreatePlainConfiguration(): void
{
    // ...
}
```

See `tests/RequirementsTest.php` for canonical examples of all four group types.
