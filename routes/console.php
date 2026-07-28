<?php

use Illuminate\Support\Facades\Schedule;

// Trash that never empties is just a second copy of everything you deleted.
Schedule::command('vault:purge')->daily();
