Plugins
=======

GNU social supports a simple but
powerful plugin architecture. Important events in the code are named,
like 'StartNoticeSave', and other software can register interest
in those events. When the events happen, the other software is called
and has a choice of accepting or rejecting the events.

In the simplest case, you can add a function to config.php and use the
Event::addHandler() function to hook an event:

    function AddMyWebsiteLink($action)
    {
        $action->menuItem('http://mywebsite.net/', _('My web site'), _('Example web link'));
        return true;
    }

    Event::addHandler('EndPrimaryNav', 'AddMyWebsiteLink');

This adds a menu item to the end of the main navigation menu. You can
see the list of existing events, and parameters that handlers must
implement, in EVENTS.txt.

The Plugin class in lib/plugin.php makes it easier to write more
complex plugins. Sub-classes can just create methods named
'onEventName', where 'EventName' is the name of the event (case
matters!). These methods will be automatically registered as event
handlers by the Plugin constructor (which you must call from your own
class's constructor).

Several example plugins are included in the plugins/ directory. You
can enable a plugin with the following line in config.php:

    addPlugin('Example', array('param1' => 'value1',
                               'param2' => 'value2'));

This will look for and load files named 'ExamplePlugin.php' or
'Example/ExamplePlugin.php' either in the plugins/ directory (for
plugins that ship with GNU social) or in the local/ directory (for
plugins you write yourself or that you get from somewhere else) or
local/plugins/.

Plugins are documented in their own directories.
