# Announcement Edit Focus Design

## Goal

Make the existing inline announcement edit workflow visibly respond when an
administrator clicks **Edit** from the announcement list.

## Behavior

- Keep the current announcement form at the top of the page.
- After loading an announcement into the form, dispatch a browser event.
- The form listens for that event, scrolls smoothly into view, focuses the
  title input, and selects its current text.
- Saving and cancelling continue to use the existing behavior.
- Publication confirmation behavior is unchanged.

## Implementation

Add an `announcement-edit-started` dispatch to the existing `edit` action.
Add an Alpine event listener to the form panel and a reference to the title
input. No new modal, component, route, or persistence behavior is required.

## Testing

Add focused source assertions for the dispatch, listener, smooth scrolling,
and title focus. Run the Sprint 4 management feature suite and frontend build.
