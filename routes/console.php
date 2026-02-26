<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bluewing:dispatch-due-posts')->everyMinute();
