<?php

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

/**
 * Base class for controllers
 *
 * @package  GNUsocial
 * @category Controller
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace App\Core;

use App\Util\Common;
use App\Util\Exception\RedirectException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class Controller extends AbstractController implements EventSubscriberInterface
{
    private array $vars       = [];
    private ?Request $request = null;

    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
    }

    public function __invoke(Request $request)
    {
        $this->request = $request;
        $class         = get_called_class();
        $method        = 'on' . ucfirst(strtolower($request->getMethod()));
        if (method_exists($class, $method)) {
            return $class::$method($request, $this->vars);
        } else {
            return $class::handle($request, $this->vars);
        }
    }

    /**
     * Symfony event when it's searching for which controller to use
     */
    public function onKernelController(ControllerEvent $event)
    {
        $controller = $event->getController();
        $request    = $event->getRequest();

        $this->request = $request;
        $this->vars    = ['controler' => $controller, 'request' => $request, 'have_user' => Common::user() !== null];
        Event::handle('StartTwigPopulateVars', [&$this->vars]);

        $event->stopPropagation();
        return $event;
    }

    /**
     * Symfony event when the controller result is not a Response object
     */
    public function onKernelView(ViewEvent $event)
    {
        $request  = $event->getRequest();
        $response = $event->getControllerResult();
        if (!is_array($response)) {
            return $event;
        }

        $this->vars = array_merge_recursive($this->vars, $response);
        Event::handle('EndTwigPopulateVars', [&$this->vars]);

        $template = $this->vars['_template'];
        unset($this->vars['_template'], $this->vars['request']);

        // Respond in the the most preffered acceptable content type
        $format = $request->getFormat($request->getAcceptableContentTypes()[0]);
        switch ($format) {
        case 'html':
            $event->setResponse($this->render($template, $this->vars));
            break;
        case 'json':
            $event->setResponse(new JsonResponse($this->vars));
            break;
        default:
            throw new BadRequestHttpException('Unsupported format', null, 406);
        }

        return $event;
    }

    /**
     * Symfony event when the controller throws an exception
     */
    public function onKernelException(ExceptionEvent $event)
    {
        $except = $event->getThrowable();
        do {
            if ($except instanceof RedirectException) {
                if (($redir = $except->redirect_response) != null) {
                    $event->setResponse($redir);
                } else {
                    $event->setResponse(new RedirectResponse($event->getRequest()->getPathInfo()));
                }
            }
        } while ($except != null && ($except = $except->getPrevious()) != null);
        return $event;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::EXCEPTION  => 'onKernelException',
            KernelEvents::VIEW       => 'onKernelView',
        ];
    }

    /**
     * Get and convert GET parameters. Can be called with `int`, `bool`, etc
     *
     * @param string $name
     *
     * @throws ValidatorException
     */
    public function __call(string $method, array $args)
    {
        $name  = $args[0];
        $value = $this->request->query->get($name);
        switch ($method) {
        case 'int':
            return (int) $value;
        case 'bool':
            return (bool) $value;
        default:
            Log::critical($m = "Method '{$method}' on class App\\Core\\Controller not found (__call)");
            throw new \Exception($m);
        }
    }
}
