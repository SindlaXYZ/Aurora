# Workflow Component - Enums, Weighted Transitions, Listener Diagrams

| Version | Created    | Updated    |
|---------|------------|------------|
| 8.1     | 2026-05-29 | 2026-05-29 |

**Sources:**
* https://symfony.com/doc/8.1/workflow.html
* https://symfony.com/doc/8.1/workflow/dumping-workflows.html
* https://raw.githubusercontent.com/symfony/symfony/refs/heads/8.1/CHANGELOG-8.1.md

The Workflow component features the stub recommends: backed-enum places and weighted transitions (both introduced on the 7.4 line, still current and documented on 8.1), plus the 8.1 addition - dumping workflow listeners inside the Graphviz diagram. All are opt-in; projects without workflows are unaffected.

## 1. Backed Enums as Places

Place names can be backed-enum cases. The framework casts between enum and backing value automatically - the 8.1 docs put it as: "The component will now transparently cast the enum to its backing value when needed and vice-versa when working with your objects."

```php
enum BlogPostStatus: string
{
    case Draft     = 'draft';
    case Reviewed  = 'reviewed';
    case Published = 'published';
    case Rejected  = 'rejected';
}
```

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        blog_post:
            type: state_machine
            marking_store: { type: 'method', property: 'status' }
            supports: [App\Entity\BlogPost]
            initial_marking: !php/enum App\Enum\BlogPostStatus::Draft
            places:
                - !php/enum App\Enum\BlogPostStatus::Draft
                - !php/enum App\Enum\BlogPostStatus::Reviewed
                - !php/enum App\Enum\BlogPostStatus::Published
                - !php/enum App\Enum\BlogPostStatus::Rejected
            transitions:
                review:
                    from: !php/enum App\Enum\BlogPostStatus::Draft
                    to:   !php/enum App\Enum\BlogPostStatus::Reviewed
                publish:
                    from: !php/enum App\Enum\BlogPostStatus::Reviewed
                    to:   !php/enum App\Enum\BlogPostStatus::Published
                reject:
                    from: [!php/enum App\Enum\BlogPostStatus::Draft, !php/enum App\Enum\BlogPostStatus::Reviewed]
                    to:   !php/enum App\Enum\BlogPostStatus::Rejected
```

When every place is a case of the same enum, the 8.1 docs also accept the whole-enum shorthand `places: !php/enum App\Enum\BlogPostStatus` (expands to all cases) - use it only when the workflow truly covers every case; the explicit per-case list above is clearer when it does not.

Rule: **prefer enums over strings for places.** Once you have ~3 places, the string form silently rots when typos slip in; enums catch them at compile time.

## 2. Weighted Transitions

Places can hold multiplicity - a token can sit in a place N times. Transitions can:

- Add N tokens to a target place (`to` with `weight: N`).
- Require N tokens in a source place before firing (`from` with `weight: N`).

This is a **Petri-net** style synchronisation primitive. Use it when a step has to wait for multiple parallel sub-tasks to complete.

```yaml
framework:
    workflows:
        assemble_table:
            type: workflow
            marking_store: { type: 'method', property: 'marking' }
            supports: [App\Entity\TableAssemblyJob]
            initial_marking: 'init'
            places: [init, prepare_leg, prepare_top, leg_created, top_created, finished]
            transitions:
                start:
                    from: init
                    to:
                        - { place: prepare_leg, weight: 4 }
                        - { place: prepare_top, weight: 1 }
                build_leg:
                    from: prepare_leg
                    to:   leg_created
                build_top:
                    from: prepare_top
                    to:   top_created
                join:
                    from:
                        - { place: leg_created, weight: 4 }
                        - { place: top_created }     # weight defaults to 1
                    to: finished
```

```php
$workflow->apply($job, 'start');       // -> 4x prepare_leg + 1x prepare_top
for ($i = 0; $i < 4; $i++) {
    $workflow->apply($job, 'build_leg');   // each call consumes 1x prepare_leg
}
$workflow->apply($job, 'build_top');
$workflow->apply($job, 'join');        // succeeds only when leg_created has 4 tokens AND top_created has 1
```

Until `join` fires, `$workflow->can($job, 'join')` returns `false`. The marking store accumulates the counts.

When defining a workflow programmatically rather than in YAML, weights are expressed with the `Symfony\Component\Workflow\Transition\Arc` class - `new Arc('prepare_leg', 4)` is the equivalent of `{ place: prepare_leg, weight: 4 }`; a bare place string defaults to weight 1.

## 3. Rules

1. **Use state_machine when one token at a time is enough.** Most "draft -> reviewed -> published" flows are state machines, NOT workflows. State machines do NOT support multiplicity.
2. **Use workflow (Petri net) for genuinely parallel work.** Approval flows (3 of 5 approvers must sign), batch assembly, multi-stage build pipelines.
3. **Default `weight: 1`.** Omitting the key is the same as `weight: 1`. Only add `weight:` when the multiplicity > 1.
4. **Persist the marking faithfully.** Method-based marking stores serialize the marking to a column. For weighted markings, the column MUST be array / JSON, not a single string - verify the doctrine mapping.
5. **Guards run AFTER weight checks.** If the weight requirement is not satisfied, guards never fire. Don't conflate the two.

## 4. Visualising the Workflow

```bash
bin/console workflow:dump assemble_table | dot -Tpng -o workflow.png
```

The dumper renders weights in edge labels.

### 4.1 Dumping Listeners in the Diagram (Symfony 8.1)

Symfony 8.1 adds a `--with-listeners` option to `workflow:dump`. It renders the workflow listeners associated with places and transitions directly inside the diagram, so the visual covers not just the state graph but the event handlers attached to it.

```bash
bin/console workflow:dump assemble_table --with-listeners | dot -Tsvg -o workflow.svg
```

Notes:

- **Introduced in 8.1** - on 8.0 and earlier the option does not exist (`workflow:dump` rejects it).
- **DOT dumper only.** `--with-listeners` is supported by the Graphviz (`dot`) dumper, NOT by the PlantUML or Mermaid dumpers. Pair it with `dot -T...` as above.
- Use it to audit which `WorkflowEvents` listeners / guards fire at each place and transition - a fast way to spot a transition that silently has no guard, or a place with an unexpected `entered` listener.

## 5. Common Errors

| Error | Cause | Fix |
|---|---|---|
| `LogicException: Transition "join" cannot be applied` | Marking does not have enough tokens | Inspect via `$workflow->getMarking($subject)->getPlaces()`. |
| Enum places ignored / treated as strings | YAML form using bare `App\Enum\Status::Draft` instead of `!php/enum` tag | Use the `!php/enum` YAML tag. |
| Marking column overflow on weighted workflow | Marking stored as a string | Switch the column type to JSON or array. |
| `--with-listeners` not recognized by `workflow:dump` | `symfony/workflow` < 8.1, OR using the PlantUML / Mermaid dumper | Upgrade to `^8.1` and use the DOT dumper (`dot -T...`). |

## 6. Version Constraints

| Package | Required |
|---|---|
| `symfony/workflow` | `^8.1` (backed-enum places and weighted transitions carried from `^7.4`; `workflow:dump --with-listeners` requires `^8.1`) |
