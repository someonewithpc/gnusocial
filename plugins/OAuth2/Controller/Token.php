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

/**
 * OAuth2 implementation for GNU social
 *
 * @package   OAuth2
 * @category  API
 *
 * @author    Diogo Peralta Cordeiro <mail@diogo.site>
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2022 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\OAuth2\Controller;

use App\Core\Controller;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Plugin\OAuth2\OAuth2;
use Psr\Http\Message\ResponseFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class Token extends Controller
{
    public function __construct(
        RequestStack $stack,
        private ResponseFactoryInterface $response_factory,
    ) {
        parent::__construct($stack);
    }

    public function __invoke(Request $request)
    {
        // @var \League\OAuth2\Server\AuthorizationServer $server
        $server           = OAuth2::$authorization_server;
        $psr17factory     = new Psr17Factory();
        $psr_http_factory = new PsrHttpFactory($psr17factory, $psr17factory, $psr17factory, $psr17factory);
        $psr_request      = $psr_http_factory->createRequest($request);

        $http_foundation_factory = new HttpFoundationFactory;
        $server_response         = $this->response_factory->createResponse();

        try {
            return $http_foundation_factory->createResponse($server->respondToAccessTokenRequest($psr_request, $server_response));
        } catch (OAuthServerException $e) {
            return $http_foundation_factory->createResponse($e->generateHttpResponse($server_response));
        }
    }
}
