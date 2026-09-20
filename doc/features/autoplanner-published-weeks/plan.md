Status: in progress — 2/4

- [x] Add `planner_open` to `published_weeks`, the endpoint that sets it, and the flag in the planning page data. Test access, set and clear, and that unpublish and publish reset it.
- [x] Make the generator skip the open spots of frozen published pairs, and fill the spots of open pairs. Test frozen, open, unfulfilled reporting, and that existing assignments never move.
- [ ] Clear the flag after a successful run of the cycle. Test success, failure, and a run for a cycle that holds no open pair.
- [ ] Add the Allow autoplanner toggle to the workcenter card. Test that it shows only when published, and that it calls the endpoint.
