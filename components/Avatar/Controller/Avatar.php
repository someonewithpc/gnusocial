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

namespace Component\Avatar\Controller;

use App\Core\Controller;
use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Form;
use App\Core\GSFile;
use App\Core\GSFile as M;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Util\Common;
use App\Util\Exception\ClientException;
use App\Util\TemporaryFile;
use Component\Avatar\Entity\Avatar as AvatarEntity;
use Exception;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Avatar extends Controller
{
    public function default_avatar_view(Request $request, string $size): Response
    {
        return $this->avatar_view($request, 0, $size);
    }

    /**
     * @throws Exception
     */
    public function avatar_view(Request $request, int $actor_id, string $size): Response
    {
        $res = \Component\Avatar\Avatar::getAvatarFileInfo($actor_id, $size);
        return M::sendFile($res['filepath'], $res['mimetype'], $res['title']);
    }

    /**
     * Local user avatar panel
     */
    public static function settings_avatar(Request $request): array
    {
        $form = Form::create([
            ['avatar', FileType::class, ['label' => _m('Avatar'), 'help' => _m('You can upload your personal avatar. The maximum file size is 2MB.'), 'multiple' => false, 'required' => false]],
            ['remove', CheckboxType::class, ['label' => _m('Remove avatar'), 'help' => _m('Remove your avatar and use the default one'), 'required' => false, 'value' => false]],
            ['hidden', HiddenType::class, []],
            ['save_avatar', SubmitType::class, ['label' => _m('Submit')]],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data     = $form->getData();
            $user     = Common::user();
            $actor_id = $user->getId();
            if ($data['remove'] == true) {
                if (\is_null($avatar = DB::findOneBy(AvatarEntity::class, ['actor_id' => $actor_id], return_null: true))) {
                    $form->addError(new FormError(_m('No avatar set, so cannot delete.')));
                } else {
                    $avatar->delete();
                }
            } else {
                $attachment = null;
                $title      = $data['avatar']?->getClientOriginalName() ?? null;
                if (isset($data['hidden'])) {
                    // Cropped client side
                    $matches = [];
                    if (!empty(preg_match('/data:([^;]*)(;(base64))?,(.*)/', $data['hidden'], $matches))) {
                        [, , , $encoding_user, $data_user] = $matches;
                        if ($encoding_user === 'base64') {
                            $data_user = base64_decode($data_user);
                            $tempfile  = new TemporaryFile(['prefix' => 'gs-avatar']);
                            $tempfile->write($data_user);
                            $attachment = GSFile::storeFileAsAttachment($tempfile);
                        } else {
                            Log::info('Avatar upload got an invalid encoding, something\'s fishy and/or wrong');
                        }
                    }
                } elseif (isset($data['avatar'])) {
                    // Cropping failed (e.g. disabled js), use file as uploaded
                    $file       = $data['avatar'];
                    $attachment = GSFile::storeFileAsAttachment($file);
                } else {
                    throw new ClientException(_m('Invalid form.'));
                }
                // Delete current avatar if there's one
                if (!\is_null($avatar = DB::findOneBy(AvatarEntity::class, ['actor_id' => $actor_id], return_null: true))) {
                    $avatar->delete();
                }
                DB::persist($attachment);
                DB::persist(AvatarEntity::create([
                    'actor_id'      => $actor_id,
                    'attachment_id' => $attachment->getId(),
                    'title'         => $title,
                ]));
                DB::flush();
                Event::handle('AvatarUpdate', [$user->getId()]);
            }
        }

        return ['_template' => 'avatar/settings.html.twig', 'avatar' => $form->createView()];
    }
}
