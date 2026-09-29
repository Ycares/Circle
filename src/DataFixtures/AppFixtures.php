<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Application\Port\PasswordHasherInterface;
use App\Domain\Entity\Book;
use App\Domain\Entity\Chapter;
use App\Domain\Entity\ClubMembership;
use App\Domain\Entity\Discussion;
use App\Domain\Entity\ReadingClub;
use App\Domain\Entity\User;
use App\Domain\ValueObject\ClubRole;
use App\Domain\ValueObject\ClubVisibility;
use App\Domain\ValueObject\EmailAddress;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Mode démo : un club, quelques membres et des discussions à différents chapitres,
 * pour illustrer le garde-spoiler par chapitre sans avoir à créer de compte.
 *
 * Identifiants de connexion (mot de passe identique pour les trois) :
 *   alice@example.com / password123  (hôte du club, a tout lu)
 *   bob@example.com   / password123  (n'a lu que le chapitre 2)
 *   chloe@example.com / password123  (a lu jusqu'au chapitre 5, en avance sur Bob)
 */
final class AppFixtures extends Fixture
{
    private const string DEMO_PASSWORD = 'password123';

    public function __construct(private readonly PasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $alice = $this->createUser('alice@example.com', 'Alice', $manager);
        $bob = $this->createUser('bob@example.com', 'Bob', $manager);
        $chloe = $this->createUser('chloe@example.com', 'Chloé', $manager);

        $book = new Book(
            'Fondation',
            ['Isaac Asimov'],
            'fr',
            null,
            'demo-foundation',
            ['Science-fiction'],
            new \DateTimeImmutable(),
        );
        $manager->persist($book);

        $club = new ReadingClub(
            'Les Fondations',
            "Club de lecture autour du cycle de Fondation d'Isaac Asimov.",
            ClubVisibility::PUBLIC,
            $alice,
            new \DateTimeImmutable(),
        );
        $club->setCurrentBook($book, 5);
        $manager->persist($club);

        $chapters = [];
        for ($number = 1; $number <= 5; ++$number) {
            $chapter = new Chapter($club, $number);
            $manager->persist($chapter);
            $chapters[$number] = $chapter;
        }

        $hostMembership = new ClubMembership($alice, $club, ClubRole::HOST, new \DateTimeImmutable());
        $hostMembership->declareChapter(5);
        $manager->persist($hostMembership);

        // Bob n'a lu que le chapitre 2 : les discussions des chapitres 3 à 5 lui restent
        // invisibles, y compris le message de Chloé ci-dessous qui spoile la fin.
        $bobMembership = new ClubMembership($bob, $club, ClubRole::MEMBER, new \DateTimeImmutable());
        $bobMembership->declareChapter(2);
        $manager->persist($bobMembership);

        $chloeMembership = new ClubMembership($chloe, $club, ClubRole::MEMBER, new \DateTimeImmutable());
        $chloeMembership->declareChapter(5);
        $manager->persist($chloeMembership);

        $manager->persist(new Discussion(
            $club,
            null,
            $alice,
            'Bienvenue dans le club ! On commence Fondation cette semaine, bonne lecture à tous 📚',
            new \DateTimeImmutable(),
        ));

        $manager->persist(new Discussion(
            $club,
            $chapters[1],
            $bob,
            "Le début est passionnant, Hari Seldon et la psychohistoire, quelle idée géniale.",
            new \DateTimeImmutable(),
        ));

        $manager->persist(new Discussion(
            $club,
            $chapters[1],
            $chloe,
            "Complètement d'accord, ça pose vraiment bien l'univers.",
            new \DateTimeImmutable(),
        ));

        $manager->persist(new Discussion(
            $club,
            $chapters[5],
            $chloe,
            'Le retournement final avec le plan Seldon est juste incroyable, je ne m\'y attendais pas du tout !',
            new \DateTimeImmutable(),
        ));

        $manager->flush();
    }

    private function createUser(string $email, string $pseudo, ObjectManager $manager): User
    {
        $user = new User(
            new EmailAddress($email),
            $this->passwordHasher->hash(self::DEMO_PASSWORD),
            $pseudo,
            'fr',
            new \DateTimeImmutable(),
        );
        $manager->persist($user);

        return $user;
    }
}
