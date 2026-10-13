<?php
layout_start("forbidden");
echo "<h1 class=\"text-4xl font-medium tracking-tight\">Access denied</h1>";
echo "<p class=\"mt-3 text-sm text-slate-600\">" . e($message ?? "You do not have permission to view this page.") . "</p>";
layout_end();
