# Package 5 Forecast Research — Files and Lifecycle

Status: future-only; fresh Simplifier audit required.

Files Discovery is the inventory complement for managed files that lack Media
rows. Discovery must list configured managed roots while excluding caches,
staging and derived artifacts. File origin alone is not a repair problem.

Durable journals are justified only when bytes or paths actually change:
import, move, rename, replace, trash, restore and purge. Reference protection,
containment, collision handling and compensation belong at those mutations,
not at database-only identity conversion.
