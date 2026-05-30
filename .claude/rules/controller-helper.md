# ControllerHelper - Decoupled Controllers

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/controller.html
* https://symfony.com/doc/8.1/service_container/autowiring.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md
* https://github.com/symfony/symfony/blob/8.1/src/Symfony/Bundle/FrameworkBundle/Controller/ControllerHelper.php

Symfony exposes the helper methods of `AbstractController` through a standalone service called `ControllerHelper`. Combined with the `#[AutowireMethodOf]` attribute, this lets a controller use `render()`, `redirectToRoute()`, `addFlash()`, `createNotFoundException()`, etc. **without inheriting from `AbstractController`**. The feature (introduced on the 7.4 line) is unchanged on 8.1 - the 8.1 docs document it under "Decoupling Controllers from Symfony", and there are no `CHANGELOG-8.1.md` entries touching `ControllerHelper` or `#[AutowireMethodOf]`. This rule carries the pattern to the v8.1 stub with the example code aligned to the actual stub files.

The stub ships:

- `src/Controller/Helper/RenderInterface.php`
- `src/Controller/Helper/RedirectToRouteInterface.php`
- `src/Controller/Helper/AddFlashInterface.php`
- `src/Controller/Example/DecoupledExampleController.php` (reference implementation)

## 1. When to Use This Pattern

Prefer this pattern when:

- The controller is part of a hexagonal / clean-architecture layer where inheriting framework classes is unwelcome.
- A test must mock only one helper (e.g. `render`) without standing up the entire `AbstractController` graph.
- The controller is small and uses only one or two helpers - injecting two closures is cheaper than a full base class.

Keep using `extends AbstractController` when:

- The controller already calls many helpers (`getUser()`, `isGranted()`, `denyAccessUnlessGranted()`, `createForm()`, `json()`, `file()`, etc.) - porting them all to interfaces costs more than it saves.
- The team is on board with the framework-tied style and there is no testing or layering pain to solve.

Both styles can coexist in the same project - decide per controller.

## 2. Canonical Pattern - Interface + `#[AutowireMethodOf]`

This mirrors the shipped `src/Controller/Example/DecoupledExampleController.php`:

```php
namespace App\Controller\Example;

use App\Controller\Helper\AddFlashInterface;
use App\Controller\Helper\RedirectToRouteInterface;
use App\Controller\Helper\RenderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\DependencyInjection\Attribute\AutowireMethodOf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/example/decoupled', name: 'example_decoupled_')]
final readonly class DecoupledExampleController
{
    public function __construct(
        #[AutowireMethodOf(ControllerHelper::class)] private RenderInterface $render,
        #[AutowireMethodOf(ControllerHelper::class)] private RedirectToRouteInterface $redirectToRoute,
        #[AutowireMethodOf(ControllerHelper::class)] private AddFlashInterface $addFlash,
    ) {}

    #[Route('/{id}', name: 'show', requirements: ['id' => Requirement::POSITIVE_INT])]
    public function show(int $id): Response
    {
        if (0 === $id) {
            ($this->addFlash)('warning', 'Invalid identifier.');

            return ($this->redirectToRoute)('example_decoupled_list');
        }

        return ($this->render)('example/decoupled/show.html.twig', ['id' => $id]);
    }

    #[Route('', name: 'list')]
    public function list(): Response
    {
        return ($this->render)('example/decoupled/list.html.twig');
    }
}
```

The Symfony 8.1 docs show a shorter form that injects `private \Closure $render` directly. The stub deliberately injects a project-owned interface instead (see Rule 1) - it keeps static analysis and IDE refactoring working.

## 3. Rules

1. **Always use an interface.** The closure-only form (`private \Closure $render`) - the one the docs show - works at runtime but defeats static analysis, refactoring, and IDE autocompletion. Always declare a project-owned interface that matches the helper signature and inject it instead.
2. **Interface lives under `App\Controller\Helper\`.** One interface per helper, named after the method (`RenderInterface`, `RedirectToRouteInterface`, `AddFlashInterface`, etc.). Do NOT bundle multiple methods into a single interface - `#[AutowireMethodOf]` maps ONE callable per parameter.
3. **The interface method MUST be `__invoke`.** `#[AutowireMethodOf]` wraps the helper as a callable; it injects a single `__invoke` per parameter. Other method names are silently ignored. The shipped interfaces follow this:

   ```php
   // src/Controller/Helper/RenderInterface.php
   interface RenderInterface
   {
       /**
        * @param array<string, mixed> $parameters
        */
       public function __invoke(string $view, array $parameters = [], ?Response $response = null): Response;
   }
   ```

4. **Signature must match `ControllerHelper`.** Mismatches (wrong return type, missing parameter) fail at container compile time with `Type ... is incompatible with ControllerHelper::<method>`. Look at `vendor/symfony/framework-bundle/Controller/ControllerHelper.php` and copy the signature.
5. **Call sites: `($this->render)(...)`, NOT `$this->render(...)`.** PHP needs the extra parentheses to invoke a property that holds a callable.
6. **Do NOT extend `AbstractController` at the same time.** Mixing the two styles in the same class wastes the upside (framework decoupling) and makes the dependencies harder to follow. Pick one per class.
7. **Final + readonly.** Controllers using this pattern are leaf classes with immutable dependencies - `final readonly` is the natural fit and prevents accidental subclassing that would re-introduce inheritance.

## 4. Adding a New Helper

To wrap a `ControllerHelper` method not yet covered by an interface in the stub:

1. Look up the exact signature in `vendor/symfony/framework-bundle/Controller/ControllerHelper.php`.
2. Create `src/Controller/Helper/<MethodName>Interface.php` with a single `__invoke()` whose signature matches the helper one-for-one (param names, types, defaults, return type).
3. Inject it in the controller with `#[AutowireMethodOf(ControllerHelper::class)] private <MethodName>Interface $<methodName>`.
4. Call with `($this-><methodName>)(...)`.

## 5. Testing

```php
final class DecoupledExampleControllerTest extends TestCase
{
    public function testShowRendersTemplate(): void
    {
        $render = $this->createMock(RenderInterface::class);
        $render->expects(self::once())
            ->method('__invoke')
            ->with('example/decoupled/show.html.twig', ['id' => 42])
            ->willReturn(new Response('rendered'));

        $controller = new DecoupledExampleController(
            $render,
            $this->createMock(RedirectToRouteInterface::class),
            $this->createMock(AddFlashInterface::class),
        );

        $response = $controller->show(42);

        self::assertSame('rendered', $response->getContent());
    }
}
```

No kernel boot, no service container, no Twig environment - the controller's collaborators are explicit and trivially mockable.

## 6. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `Cannot autowire service "App\Controller\Example\Foo": parameter "$render" needs an attribute or a class type-hint` | Forgot `#[AutowireMethodOf(...)]` | Add the attribute. |
| `Type ... is incompatible with ControllerHelper::render` | Interface signature drifted from the helper | Re-align the interface with `ControllerHelper::<method>` signature. |
| `Object of type Closure is not callable` (TypeError at call site) | Called without extra parentheses: `$this->render('...')` | Use `($this->render)('...')`. |
| Method silently does nothing | Interface declares a method other than `__invoke` | Rename it to `__invoke`. |

## 7. Version Constraints

| Package | Required |
|---|---|
| `symfony/framework-bundle` | `^8.1` (provides `ControllerHelper`; introduced on the 7.4 line, unchanged on 8.1) |
| `symfony/dependency-injection` | `^8.1` (provides `#[AutowireMethodOf]`; introduced on the 7.4 line, unchanged on 8.1) |
