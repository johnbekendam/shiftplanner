Status: in progress — 1/5

- [x] Add the `informed_at` and `assignment_ids` columns, the `Planning` message type with its seed template, and a service that finds uninformed planning. Test the service.
- [ ] Add the `:planning` placeholder. Make Compose store `assignment_ids` on Planning messages. Test the placeholder and the stored ids.
- [ ] Make the queue job set `informed_at` on the listed assignments after a real send. Test success, failed send, and draft.
- [ ] Add the Uninformed planning report tab and the Compose handoff. Test the report rows, the filter, and the handoff link.
- [ ] Add the Send planning button, its endpoint, and the confirm dialog on the planning page. Test the disabled state, the confirm, and the queued messages.
