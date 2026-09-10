<?php

namespace App\Tests\Reservation;

use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Admin-editable menu: dishes render on the homepage in admin-defined order.
 */
class AdminMenuTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient(server: [
            'PHP_AUTH_USER' => 'stevie',
            'PHP_AUTH_PW' => 'intasek1967',
        ]);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->em);
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    public function testHomepageShowsDishesInOrder(): void
    {
        $this->seed('First Dish', 1);
        $this->seed('Second Dish', 2);

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $items = $crawler->filter('ul li')->each(static fn ($node) => trim($node->text()));
        $first = array_search('First Dish', $items, true);
        $second = array_search('Second Dish', $items, true);
        self::assertNotFalse($first);
        self::assertNotFalse($second);
        self::assertLessThan($second, $first);
    }

    public function testAdminCanAddRenameMoveAndDeleteDish(): void
    {
        $this->seed('Alpha', 1);
        $this->seed('Beta', 2);

        // Add.
        $this->client->request('POST', '/admin/menu', [
            '_csrf_token' => $this->adminCsrfToken(),
            'name' => 'Gamma',
        ]);
        self::assertResponseRedirects('/admin/menu');
        self::assertNotNull($this->findDish('Gamma'));

        // Homepage picks it up.
        $crawler = $this->client->request('GET', '/');
        self::assertStringContainsString('Gamma', $crawler->text());

        // Rename.
        $beta = $this->findDish('Beta');
        $this->client->request('POST', "/admin/menu/{$beta->getId()}/rename", [
            '_csrf_token' => $this->adminCsrfToken(),
            'name' => 'Beta Renamed',
        ]);
        self::assertResponseRedirects('/admin/menu');
        self::assertNotNull($this->findDish('Beta Renamed'));

        // Move Alpha down past Beta Renamed.
        $alpha = $this->findDish('Alpha');
        $this->client->request('POST', "/admin/menu/{$alpha->getId()}/move", [
            '_csrf_token' => $this->adminCsrfToken(),
            'direction' => 'down',
        ]);
        self::assertResponseRedirects('/admin/menu');
        $this->em->clear();
        $names = array_map(
            static fn (MenuItem $dish): string => $dish->getName(),
            $this->em->getRepository(MenuItem::class)->findAllOrdered(),
        );
        self::assertSame(['Beta Renamed', 'Alpha', 'Gamma'], $names);

        // Delete.
        $this->client->request('POST', "/admin/menu/{$alpha->getId()}/delete", [
            '_csrf_token' => $this->adminCsrfToken(),
        ]);
        self::assertResponseRedirects('/admin/menu');
        self::assertNull($this->findDish('Alpha'));
    }

    public function testEmptyNameIsRejected(): void
    {
        $this->client->request('POST', '/admin/menu', [
            '_csrf_token' => $this->adminCsrfToken(),
            'name' => '   ',
        ]);
        self::assertResponseRedirects('/admin/menu');
        self::assertCount(0, $this->em->getRepository(MenuItem::class)->findAll());
    }

    private function seed(string $name, int $position): void
    {
        $this->em->persist(new MenuItem($name, $position));
        $this->em->flush();
    }

    private function findDish(string $name): ?MenuItem
    {
        return $this->em->getRepository(MenuItem::class)->findOneBy(['name' => $name]);
    }

    private function adminCsrfToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/menu');
        self::assertResponseIsSuccessful();

        return $crawler->filter('input[name="_csrf_token"]')->first()->attr('value');
    }
}
