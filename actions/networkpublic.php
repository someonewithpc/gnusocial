<?php

if (!defined('GNUSOCIAL')) { exit(1); }

class NetworkpublicAction extends SitestreamAction
{
    protected function streamPrepare()
    {
        if (!$this->scoped instanceof Profile && common_config('public', 'localonly')) {
            $this->clientError(_('Network wide public feed is not permitted without authorization'), 403);
        }
        if ($this->scoped instanceof Profile && $this->scoped->isLocal() && $this->scoped->getUser()->streamModeOnly()) {
            $this->stream = new NetworkPublicNoticeStream($this->scoped);
        } else {
            $this->stream = new ThreadingNetworkPublicNoticeStream($this->scoped);
        }
    }

    function title()
    {
        if ($this->page > 1) {
            // TRANS: Title for all public timeline pages but the first.
            // TRANS: %d is the page number.
            return sprintf(_('Network public timeline, page %d'), $this->page);
        } else {
            // TRANS: Title for the first public timeline page.
            return _('Network public timeline');
        }
    }

    function showSections()
    {
        // Show invite button, as long as site isn't closed, and
        // we have a logged in user.
        if (common_config('invite', 'enabled') && !common_config('site', 'closed') && common_logged_in()) {
            if (!common_config('site', 'private')) {
                $ibs = new InviteButtonSection(
                    $this,
                    // TRANS: Button text for inviting more users to the StatusNet instance.
                    // TRANS: Less business/enterprise-oriented language for public sites.
                    _m('BUTTON', 'Send invite')
                );
            } else {
                $ibs = new InviteButtonSection($this);
            }
            $ibs->show();
        }

        // Network public tag cloud?
    }

    /**
     * Output <head> elements for RSS and Atom feeds
     *
     * @return array
     */
    function getFeeds()
    {
        return [
            new Feed(Feed::ATOM,
                common_local_url('ApiTimelinePublic',
                    array('format' => 'atom')),
                // TRANS: Link description for public timeline feed.
                _('Public Timeline Feed (Atom)')
            ),
            new Feed(Feed::JSON,
                common_local_url('ApiTimelinePublic',
                    array('format' => 'as')),
                // TRANS: Link description for public timeline feed.
                _('Public Timeline Feed (Activity Streams JSON)')
            ),
            new Feed(Feed::RSS1, common_local_url('publicrss'),
                // TRANS: Link description for public timeline feed.
                _('Public Timeline Feed (RSS 1.0)')
            ),
            new Feed(Feed::RSS2,
                common_local_url('ApiTimelinePublic',
                    array('format' => 'rss')),
                // TRANS: Link description for public timeline feed.
                _('Public Timeline Feed (RSS 2.0)')
            ),
        ];
    }
}
