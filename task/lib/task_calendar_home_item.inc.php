<?php

/*
 * Copyright (C) 2006-2011 Alex Lance, Clancy Malcolm, Cyber IT Solutions
 * Pty. Ltd.
 * 
 * This file is part of the allocPSA application <info@cyber.com.au>.
 * 
 * allocPSA is free software: you can redistribute it and/or modify it
 * under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or (at
 * your option) any later version.
 * 
 * allocPSA is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public
 * License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with allocPSA. If not, see <http://www.gnu.org/licenses/>.
*/

class task_calendar_home_item extends home_item {
  var $date;

  function task_calendar_home_item() {
    home_item::home_item("task_calendar_home_item", "Calendar", "task", "taskCalendarS.tpl","standard",30);
  }

  function show_task_calendar_recursive() {
    global $current_user;
    $tasksGraphPlotHomeStart = $current_user->prefs["tasksGraphPlotHomeStart"];
    $tasksGraphPlotHome = $current_user->prefs["tasksGraphPlotHome"];

    $calendar = new calendar($tasksGraphPlotHomeStart,$tasksGraphPlotHome);
    $calendar->set_cal_person($current_user->get_id());
    $calendar->set_return_mode("home");
    $calendar->draw($template);
  }
}



?>
