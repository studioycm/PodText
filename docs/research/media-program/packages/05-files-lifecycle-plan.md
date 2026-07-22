# Package 5 Forecast Plan — Files and Lifecycle

Status: not approved.

After a fresh audit:

1. Build bounded Files Discovery for rowless files in configured managed roots.
2. Import one reviewed file to create its Media row before selection.
3. Add explicit move, rename, replace, trash, restore and purge operations.
4. Journal only those real physical mutations with copy/verify/switch/cleanup
   and compensation as appropriate.
5. Block deletion/purge while active owner/settings references exist.
6. Add lifecycle/trash fields only with this UI and state machine.
7. Keep every row visible in All Media throughout repair/lifecycle states unless
   intentionally in Trash.
