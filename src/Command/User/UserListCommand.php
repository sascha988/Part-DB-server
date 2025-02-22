<?php
/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 *  Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published
 *  by the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace App\Command\User;

use App\Entity\UserSystem\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserListCommand extends Command
{
    protected static $defaultName = 'partdb:users:list|users:list';

    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Lists all users')
            ->setHelp('This command lists all users in the database.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        //Get all users from database
        $users = $this->entityManager->getRepository(User::class)->findAll();

        $io->info(sprintf("Found %d users in database.", count($users)));

        $io->title('Users:');

        $table = new Table($output);
        $table->setHeaders(['ID', 'Username', 'Name', 'Email', 'Group', 'Login Disabled']);

        foreach ($users as $user) {
            $table->addRow([
                $user->getId(),
                $user->getUsername(),
                $user->getFullName(),
                $user->getEmail(),
                $user->getGroup() !== null ? $user->getGroup()->getName() . ' (ID: ' . $user->getGroup()->getID() . ')' : 'No group',
                $user->isDisabled() ? 'Yes' : 'No',
            ]);
        }

        $table->render();


        return self::SUCCESS;
    }

}