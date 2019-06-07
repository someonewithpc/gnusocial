<?php

class OpportunisticQMPlugin extends Plugin {
    const PLUGIN_VERSION = '2.0.0';

    public $qmkey = false;
    public $secs_per_action = 1; // total seconds to run script per action
    public $rel_to_pageload = true;  // relative to pageload or queue start
    public $verbosity = 1;

    public function onRouterInitialized($m)
    {
        $m->connect('main/runqueue', array('action' => 'runqueue'));
    }

    /**
     * When the page has finished rendering, let's do some cron jobs
     * if we have the time.
     */
    public function onEndActionExecute(Action $action)
    {
        if ($action instanceof RunqueueAction) {
            return true;
        }

        global $_startTime;

        $args = array(
                    'qmkey' => common_config('opportunisticqm', 'qmkey'),
                    'max_execution_time' => $this->secs_per_action,
                    'started_at'      => $this->rel_to_pageload ? $_startTime : null,
                    'verbosity'          => $this->verbosity,
                );
        $qm = new OpportunisticQueueManager($args); 
        $qm->runQueue();
        return true;
    }

    public function onPluginVersion(array &$versions)
    {
        $versions[] = array('name' => 'OpportunisticQM',
                            'version' => self::PLUGIN_VERSION,
                            'author' => 'Mikael Nordfeldth',
                            'homepage' => 'http://www.gnu.org/software/social/',
                            'description' =>
                            // TRANS: Plugin description.
                            _m('Opportunistic queue manager plugin for background processing.'));
        return true;
    }
}
