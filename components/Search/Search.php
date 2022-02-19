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

namespace Component\Search;

use App\Core\Event;
use App\Core\Form;
use function App\Core\I18n\_m;
use App\Core\Modules\Component;
use App\Util\Common;
use App\Util\Exception\RedirectException;
use App\Util\Formatting;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\SubmitButton;
use Symfony\Component\HttpFoundation\Request;

class Search extends Component
{
    public function onAddRoute($r)
    {
        $r->connect('search', '/search', Controller\Search::class);
    }

    /**
     * Helper function for generating and processing the search form, so it can be embedded in
     * multiple places. Can be provided with a $query, which will prefill the query field. If
     * $add_subscribe, allow the user to add the current query to their left panel
     */
    public static function searchForm(Request $request, ?string $query = null, bool $add_subscribe = false): FormView
    {
        $actor = Common::actor();
        if (\is_null($actor)) {
            $add_subscribe = false;
        }

        $form_definition = [
            ['search_query', TextType::class, [
                'attr' => ['placeholder' => _m('Input desired query...'), 'value' => $query],
            ]],
        ];

        if ($add_subscribe) {
            $form_definition[] = [
                'title', TextType::class,
                [
                    'label'    => _m('Subscribe to search query'),
                    'help'     => _m('By subscribing to a search query, a new feed link will be added to left panel\'s feed navigation menu'),
                    'required' => false,
                    'attr'     => [
                        'title'       => _m('Title for this new feed in your left panel'),
                        'placeholder' => _m('Input desired title...'),
                    ],
                ],
            ];
            $form_definition[] = [
                'subscribe_to_search',
                SubmitType::class,
                [
                    'label' => _m('Subscribe'),
                    'attr'  => [
                        'title' => _m('Add this search as a feed in your feeds section of the left panel'),
                    ],
                ],
            ];
        }

        $form_definition[] = [
            $form_name = 'submit_search',
            SubmitType::class,
            [
                'label' => _m('Search'),
                'attr'  => [
                    //'class' => 'button-container search-button-container',
                    'title' => _m('Perform search'),
                ],
            ],
        ];

        $form = Form::create($form_definition);

        if ('POST' === $request->getMethod() && $request->request->has($form_name)) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $data     = $form->getData();
                $redirect = false;
                if ($add_subscribe) {
                    /** @var SubmitButton $subscribe */
                    $subscribe = $form->get('subscribe_to_search');
                    if ($subscribe->isClicked()) {
                        // TODO ensure title is set
                        Event::handle('AppendFeed', [$actor, $data['title'], 'search', ['q' => $data['search_query']]]);
                        $redirect = true;
                    }
                }
                /** @var SubmitButton $submit */
                $submit = $form->get($form_name);
                if ($submit->isClicked() || $redirect) {
                    throw new RedirectException('search', ['q' => $data['search_query']]);
                }
            }
        }
        return $form->createView();
    }

    /**
     * Add the search form to the site header
     *
     * @throws RedirectException
     */
    public function onPrependRightPanelBlock(Request $request, array &$elements): bool
    {
        $elements[] = Formatting::twigRenderFile('cards/search/view.html.twig', ['search' => self::searchForm($request)]);
        return Event::next;
    }

    /**
     * Output our dedicated stylesheet
     *
     * @param array $styles stylesheets path
     *
     * @return bool hook value; true means continue processing, false means stop
     */
    public function onEndShowStyles(array &$styles, string $route): bool
    {
        $styles[] = 'components/Search/assets/css/view.css';
        return Event::next;
    }
}
