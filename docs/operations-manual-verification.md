# Operations manual verification checklist

Apply the Operations migrations to a disposable database and sign in to the railroad under test.

1. **Base is not destination:** Place one car and a locomotive at Cargill and cars at several customers. Generate a high maximum. Confirm no car has identical origin/destination and customer pulls are sent only to an explicit terminal/yard, never universally to Cargill.
2. **Multiple assignments:** Create session `S00042`; add A, B, and C; leave and reopen it. Confirm all three cards and snapshots remain.
3. **No duplicate resources:** Approve A, then try its locomotive/car in unrelated B. Confirm the save is rejected. Explicit downstream inheritance remains allowed.
4. **Variable cuts:** Build Ready cuts of 2, 10, and 20 cars (where roster/capacity permits). Select “Travel light,” generate, and confirm the pickup instruction precedes work with no ten-car assumption.
5. **Chaining:** End A at Yard 2 and make B inherit from A. Confirm B is Waiting, then Ready after a clean A completion.
6. **Dependency exception:** Complete an A car as Not Moved. Confirm B becomes Needs Review.
7. **Print/return:** Approve and print, sign out, reopen from Switch Lists, enter actual results, and complete.
8. **Progress:** Check a move, visit another page, and return. Confirm it remains checked and Equipment did not move.
9. **Completion:** Mark mixed Moved/Not Moved results. Confirm only Moved cars change location and every exception remains on the saved list.
10. **Cancellation:** Cancel an approved list and confirm no car or locomotive moved and the record remains searchable.
11. **Stale state:** After approval, change a planned car's physical location. Completion must reject the entire transaction without partial updates.
12. **Photos:** Confirm thumbnail/No Photo, X/backdrop/Escape close behavior, focus return, and usable print sizing.
13. **Mobile:** At a narrow viewport, check session cards, assignment form, horizontally scrolling work table, closeout controls, and lightbox.
