<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Tests\Services\Trees;

use App\Entity\Attachments\AttachmentType;
use App\Entity\Parts\Category;
use App\Helpers\Trees\TreeViewNode;
use App\Services\Trees\TreeViewGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * @group DB
 */
class TreeViewGeneratorTest extends WebTestCase
{
    protected $em;
    /**
     * @var TreeViewGenerator
     */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        //Get an service instance.
        self::bootKernel();
        $this->service = self::$container->get(TreeViewGenerator::class);
        $this->em = self::$container->get(EntityManagerInterface::class);
    }

    public function testGetGenericTree(): void
    {
        $tree = $this->service->getGenericTree(AttachmentType::class, null);

        $this->assertIsArray($tree);
        $this->assertContainsOnlyInstancesOf(TreeViewNode::class, $tree);

        $this->assertCount(3, $tree);
        $this->assertCount(2, $tree[0]->getNodes());
        $this->assertCount(1, $tree[0]->getNodes()[0]->getNodes());
        $this->assertEmpty($tree[2]->getNodes());
        $this->assertEmpty($tree[1]->getNodes()[0]->getNodes());

        //Check text
        $this->assertSame('Node 1', $tree[0]->getText());
        $this->assertSame('Node 2', $tree[1]->getText());
        $this->assertSame('Node 3', $tree[2]->getText());
        $this->assertSame('Node 1.1', $tree[0]->getNodes()[0]->getText());
        $this->assertSame('Node 1.1.1', $tree[0]->getNodes()[0]->getNodes()[0]->getText());

        //Check that IDs were set correctly
        $this->assertSame(1, $tree[0]->getId());
        $this->assertSame(2, $tree[1]->getId());
        $this->assertSame(7, $tree[0]->getNodes()[0]->getNodes()[0]->getId());
    }

    public function testGetTreeViewBasic(): void
    {
        $tree = $this->service->getTreeView(Category::class);
        $this->assertIsArray($tree);
        $this->assertContainsOnlyInstancesOf(TreeViewNode::class, $tree);

        $this->assertCount(3, $tree);
        $this->assertCount(2, $tree[0]->getNodes());
        $this->assertCount(1, $tree[0]->getNodes()[0]->getNodes());

        //Assert that the nodes contain the correct links
        $this->assertSame('/en/category/1/parts', $tree[0]->getHref());
        $this->assertSame('/en/category/2/parts', $tree[1]->getHref());
        $this->assertSame('/en/category/7/parts', $tree[0]->getNodes()[0]->getNodes()[0]->getHref());
    }

    public function testGetTreeViewNewEdit(): void
    {
        $tree = $this->service->getTreeView(Category::class, null, 'newEdit');

        //First element should link to new category
        $this->assertStringContainsStringIgnoringCase('New', $tree[0]->getText());
        $this->assertSame('/en/category/new', $tree[0]->getHref());
        //By default the new element node is selected
        $this->assertTrue($tree[0]->getState()->getSelected());

        //Next element is spacing
        $this->assertSame('', $tree[1]->getText());
        $this->assertTrue($tree[1]->getState()->getDisabled());

        //All other elements should be normal
        $this->assertCount(5, $tree);
    }

    public function testGetTreeViewSelectedNode(): void
    {
        $selected = $this->em->find(Category::class, 2);
        $tree = $this->service->getTreeView(Category::class, null, 'edit', $selected);

        $this->assertNull($tree[0]->getState());
        //Only second element must be selected
        $this->assertTrue($tree[1]->getState()->getSelected());
    }
}
