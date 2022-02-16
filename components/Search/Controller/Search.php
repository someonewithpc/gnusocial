<?php

declare(strict_types = 1);

// {{{ License

// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.

// }}}

namespace Component\Search\Controller;

use App\Core\Form;
use function App\Core\I18n\_m;
use App\Util\Common;
use App\Util\Exception\BugFoundException;
use App\Util\Exception\RedirectException;
use App\Util\Form\FormFields;
use App\Util\Formatting;
use App\Util\HTML\Heading;
use Component\Collection\Util\Controller\FeedController;
use Component\Search as Comp;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

class Search extends FeedController
{
    /**
     * Handle a search query
     */
    public function handle(Request $request)
    {
        $actor    = Common::actor();
        $language = !\is_null($actor) ? $actor->getTopLanguage()->getLocale() : null;
        $q        = $this->string('q');

        $data   = $this->query(query: $q, locale: $language);
        $notes  = $data['notes'];
        $actors = $data['actors'];

        $search_builder_form = Form::create([
            ['include_actors', CheckboxType::class, ['required' => false, 'data' => false, 'label' => _m('Include people/actors')]],
            ['include_actors_groups', CheckboxType::class, ['required' => false, 'data' => false, 'label' => _m('Include groups')]],
            ['include_actors_lists', CheckboxType::class, ['required' => false, 'data' => false, 'label' => _m('Include people lists')]],
            ['include_actors_people', CheckboxType::class, ['required' => false, 'data' => false, 'label' => _m('Include people')]],
            ['include_actors_businesses', CheckboxType::class, ['required' => false, 'data' => false, 'label' => _m('Include businesses')]],
            ['include_actors_organizations', CheckboxType::class, ['required' => false, 'data' => false, 'label' => _m('Include organizations')]],
            ['include_actors_bots', CheckboxType::class, ['required' => false, 'data' => false, 'label' => _m('Include bots')]],
            ['include_notes', CheckboxType::class, ['required' => false, 'data' => true, 'label' => _m('Include notes')]],
            ['include_notes_text', CheckboxType::class, ['required' => false, 'data' => true, 'label' => _m('Include text notes')]],
            ['include_notes_media', CheckboxType::class, ['required' => false, 'data' => true, 'label' => _m('Include media notes')]],
            ['include_notes_polls', CheckboxType::class, ['required' => false, 'data' => true, 'label' => _m('Include polls')]],
            ['include_notes_bookmarks', CheckboxType::class, ['required' => false, 'data' => true, 'label' => _m('Include bookmarks')]],
            /* note_langs */ FormFields::language($actor, context_actor: null, label: _m('Search for notes in these languages'), multiple: true, required: false, use_short_display: false, form_id: 'note_langs', use_no_selection: true),
            ['note_tags', TextType::class, ['required' => false, 'label' => _m('Include only notes with all the following tags')]],
            /* note_actor_langs */ FormFields::language($actor, context_actor: null, label: _m('Search for notes by people who know these languages'), multiple: true, required: false, use_short_display: false, form_id: 'note_actor_langs', use_no_selection: true),
            ['note_actor_tags', TextType::class, ['required' => false, 'label' => _m('Include only notes by people with all the following tags')]],
            /* actor_langs */ FormFields::language($actor, context_actor: null, label: _m('Search for people that know these languages'), multiple: true, required: false, use_short_display: false, form_id: 'actor_langs', use_no_selection: true),
            ['actor_tags', TextType::class, ['required' => false, 'label' => _m('Include only people with all the following tags')]],
            [$form_name = 'search_builder', SubmitType::class, ['label' => _m('Search')]],
        ]);

        if ('POST' === $request->getMethod() && $request->request->has($form_name)) {
            $search_builder_form->handleRequest($request);
            if ($search_builder_form->isSubmitted() && $search_builder_form->isValid()) {
                $data                 = $search_builder_form->getData();
                $query                = [];
                $include_notes_query  = [];
                $include_actors_query = [];
                $exception            = new BugFoundException('Search builder form seems to have new fields the code did not expect');

                foreach ($data as $key => $value) {
                    if (!\is_null($value) && !empty($value)) {
                        if (str_contains($key, 'tags')) {
                            $query[] = "{$key}:#{$value}";
                        } elseif (str_contains($key, 'lang')) {
                            if (!\in_array('null', $value)) {
                                $langs   = implode(',', $value);
                                $query[] = "{$key}:{$langs}";
                            }
                        } elseif (str_contains($key, 'include')) {
                            if (str_contains($key, 'notes')) {
                                if ($key === 'include_notes') {
                                    if (!$data[$key]) {
                                        $include_notes_query = null;
                                    }
                                } elseif ($data[$key] && !\is_null($include_notes_query)) {
                                    $include_notes_query[] = Formatting::removePrefix($key, 'include_notes_');
                                }
                            } elseif (str_contains($key, 'actors')) {
                                if ($key === 'include_actors') {
                                    if (!$data[$key]) {
                                        $include_actors_query = null;
                                    }
                                } elseif ($data[$key] && !\is_null($include_actors_query)) {
                                    $include_actors_query[] = Formatting::removePrefix($key, 'include_actors_');
                                }
                            } else {
                                throw $exception;
                            }
                        } else {
                            throw $exception;
                        }
                    }
                }

                if (!\is_null($include_notes_query) && !empty($include_notes_query)) {
                    $query[] = 'note-types:' . implode(',', $include_notes_query);
                }
                if (!\is_null($include_actors_query) && !empty($include_actors_query)) {
                    $query[] = 'actor-types:' . implode(',', $include_actors_query);
                }
                $query = implode(' ', $query);
                throw new RedirectException('search', ['q' => $query]);
            }
        }

        return [
            '_template'           => 'search/view.html.twig',
            'actor'               => $actor,
            'search_form'         => Comp\Search::searchForm($request, query: $q, add_subscribe: !\is_null($actor)),
            'search_builder_form' => $search_builder_form->createView(),
            'notes'               => $notes ?? [],
            'notes_feed_title'    => (new Heading(level: 3, classes: ['section-title'], text: 'Notes found')),
            'actors_feed_title'   => (new Heading(level: 3, classes: ['section-title'], text: 'Actors found')),
            'actors'              => $actors ?? [],
            'page'                => 1, // TODO paginate
        ];
    }
}
