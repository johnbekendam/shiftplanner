# Autoplanner in Published Weeks

## Problem

A planner wants the autoplanner to improve a workcenter-week that is already published. Today Generate treats every existing assignment in a published workcenter-week as locked. But it also fills the open spots of that week on every run. The planner cannot stop this, and cannot ask for it on purpose. New shifts appear in published weeks without a decision by a person.

## Solution

A published workcenter-week is frozen for the autoplanner. The planner can allow one run.

- **Frozen by default.** Generate removes the open spots of a frozen published workcenter-week from its problem. Neither the construction step nor the hill-climbing step can place anyone in those spots. Existing assignments stay locked, as now. They still count for overlaps and weekly hours.
- **Allow autoplanner.** The workcenter card on the planning page gets a toggle, **Allow autoplanner**, next to Unpublish. The toggle shows only while the workcenter is published for that week.
- **One run.** The next Generate run that succeeds for the cycle of that week fills the open spots of the workcenter-week. Then the toggle switches itself off and the week is frozen again. A failed run leaves the toggle on.
- **Fill only.** The autoplanner adds employees to open spots. It does not move, replace, or remove an existing assignment of that week.

### Data

A new boolean `planner_open` on `published_weeks`, default false. Publishing a workcenter creates the row with `planner_open` false. Unpublishing deletes the row, so the flag goes with it.

A new endpoint sets the flag for one (week, workcenter) pair. It sits in the admin route group, next to the publish routes. The planning page gets the flag for the pairs of the visible month with `publishedWorkcenterWeeks`.

`PublishedWeek::lockedPairs()` stays the single source for "published". A new helper returns the published pairs with `planner_open` true. `HeuristicPlanGenerator` uses both:

- A spot of a published pair that is not open is not part of the problem.
- After a successful run, the generator sets `planner_open` to false for the open pairs of its cycle. It does this in the same transaction that applies the changes.

### Effects on other features

- New assignments have no `informed_at`. They appear in the Uninformed planning report, and Send planning picks them up.
- The change summary lists them as added.
- The generator does not try frozen spots, so it reports no unfulfilled spot for them.
- Clear Planning is unchanged. It never deletes published assignments.

## Key Decisions

- **Frozen by default.** A person who publishes a week expects it to stay as published. The current fill-in behavior was a side effect, not a decision.
- **Fill only.** Moving or removing an assignment that the employee may know about needs a notice for changed shifts. That feature does not exist. It is out of scope.
- **The flag lasts for one run.** A flag that stays on is easy to forget, and the week would keep changing.
- **Reuse Generate.** The toggle needs no new button, run type, status, or change summary.
- **The flag is on the published row.** The flag has meaning only for a published pair, and unpublishing must remove it.

## Non-goals

- A run for one workcenter-week only. Generate still re-plans the unpublished weeks as it does today.
- Moving or removing existing assignments in a published week.
- Notices for shifts that were moved or removed.
- A flag that stays on until a person switches it off.
