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

namespace App\Tests\Core\DB;

use App\Core\DB\UpdateListener;
use App\Entity\GSActor;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UpdateListenerTest extends KernelTestCase
{
    public function testPreUpdate()
    {
        static::bootKernel();
        $actor = new GSActor();
        $date  = new DateTime('1999-09-23');
        $actor->setModified($date);
        static::assertSame($actor->getModified(), $date);

        $em  = $this->createMock(EntityManager::class);
        $uow = $this->createMock(UnitOfWork::class);
        $em->expects(static::once())
           ->method('getUnitOfWork')
           ->willReturn($uow);

        $md = $this->createMock(ClassMetadata::class);
        $em->expects(static::once())
           ->method('getClassMetadata')
           ->willReturn($md);

        $change_set = [];
        $args       = new PreUpdateEventArgs($actor, $em, $change_set);
        $ul         = new UpdateListener();
        $ul->preUpdate($args);

        static::assertNotSame($actor->getModified(), $date);
    }
}