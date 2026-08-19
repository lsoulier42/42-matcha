<?php

declare(strict_types=1);

/*
 * Seed: generate 500+ demo profiles (spec requirement).
 *
 * Usage: docker compose exec web php scripts/seed.php [--force]
 *   - no argument: refuses if users already exist;
 *   - --force: truncates tables and regenerates everything.
 *
 * Seed profiles:
 *   - shared password: SeedPass123! (documented in the README);
 *   - profile photos generated locally with GD (no external resources);
 *   - likes/visits/messages consistent with orientation (seed likes
 *     respect the same compatibility as the suggestion algorithm);
 *   - popularity recomputed by PopularityService (documented formula).
 */

require __DIR__ . '/../vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Db\ConnectionFactory;
use App\Db\Query;
use App\Services\PopularityService;
use Faker\Factory;

$settings = require __DIR__ . '/../config/settings.php';
$q = new Query(ConnectionFactory::create($settings['db']));

$force = in_array('--force', $argv, true);

if ((int) $q->value('SELECT COUNT(*) FROM users') > 0 && !$force) {
    fwrite(STDERR, "Database already contains users. Use --force to regenerate everything.\n");
    exit(1);
}

$start = microtime(true);

if ($force) {
    $q->run('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['notifications', 'messages', 'reports', 'blocks', 'unlikes', 'visits', 'likes',
        'photos', 'user_tags', 'tags', 'tokens', 'users'] as $table) {
        $q->run("TRUNCATE TABLE $table");
    }
    $q->run('SET FOREIGN_KEY_CHECKS = 1');
    echo "Tables truncated.\n";
}

$faker = Factory::create('fr_FR');

// ------------------------------------------------------------
// French cities (real coordinates + slight noise)
// ------------------------------------------------------------
$villes = [
    ['Paris', 48.8566, 2.3522], ['Lyon', 45.7640, 4.8357], ['Marseille', 43.2965, 5.3698],
    ['Bordeaux', 44.8378, -0.5792], ['Lille', 50.6292, 3.0573], ['Toulouse', 43.6047, 1.4442],
    ['Nantes', 47.2184, -1.5536], ['Strasbourg', 48.5734, 7.7521], ['Nice', 43.7102, 7.2620],
    ['Montpellier', 43.6108, 3.8767], ['Rennes', 48.1173, -1.6778], ['Grenoble', 45.1885, 5.7245],
    ['Dijon', 47.3220, 5.0415], ['Tours', 47.3941, 0.6848], ['Clermont-Ferrand', 45.7772, 3.0870],
    ['Le Havre', 49.4944, 0.1079], ['Reims', 49.2583, 4.0317], ['Saint-Étienne', 45.4397, 4.3872],
    ['Toulon', 43.1242, 5.9280], ['Annecy', 45.8992, 6.1294], ['Angers', 47.4784, -0.5632],
    ['Brest', 48.3904, -4.4861], ['Metz', 49.1193, 6.1757], ['Caen', 49.1829, -0.3707],
    ['Perpignan', 42.6887, 2.8948], ['Pau', 43.2951, -0.3708], ['Nancy', 48.6921, 6.1844],
    ['Avignon', 43.9493, 4.8055], ['Bayonne', 43.4929, -1.4748], ['Lorient', 47.7483, -3.3701],
    ['Chambéry', 45.5646, 5.9178], ['Amiens', 49.8941, 2.2958], ['Limoges', 45.8336, 1.2611],
    ['Valence', 44.9334, 4.8924], ['La Rochelle', 46.1603, -1.1511],
];

// ------------------------------------------------------------
// Reusable tags (starter pool)
// ------------------------------------------------------------
$tagPool = [
    'vegan', 'geek', 'sport', 'musique', 'voyage', 'cuisine', 'cinema', 'lecture',
    'randonnee', 'photographie', 'animaux', 'jeux-video', 'art', 'danse', 'yoga',
    'running', 'montagne', 'plage', 'series', 'theatre', 'mode', 'gastronomie',
    'nature', 'bricolage', 'jardinage', 'moto', 'surf', 'escalade', 'peche',
    'echecs', 'programmation', 'manga', 'karaoke', 'bowling', 'tennis', 'ski',
    'voile', 'equitation', 'vtt', 'jazz',
];

$tagIds = [];
foreach ($tagPool as $name) {
    $q->insert('tags', ['name' => $name]);
    $tagIds[$name] = $q->lastInsertId();
}

// ------------------------------------------------------------
// Avatar generation utilities (GD, no external resources)
// ------------------------------------------------------------
function makeAvatar(string $path, string $initials, array $c1, array $c2): void
{
    $size = 300;
    $img = imagecreatetruecolor($size, $size);

    // Vertical gradient between two colours.
    for ($y = 0; $y < $size; $y++) {
        $t = $y / ($size - 1);
        $r = (int) round($c1[0] + ($c2[0] - $c1[0]) * $t);
        $g = (int) round($c1[1] + ($c2[1] - $c1[1]) * $t);
        $b = (int) round($c1[2] + ($c2[2] - $c1[2]) * $t);
        imageline($img, 0, $y, $size, $y, imagecolorallocate($img, $r, $g, $b));
    }

    // Translucent decorative circles.
    imagefilledellipse($img, (int) ($size * 0.82), (int) ($size * 0.16), 170, 170, imagecolorallocatealpha($img, 255, 255, 255, 110));
    imagefilledellipse($img, (int) ($size * 0.10), (int) ($size * 0.88), 230, 230, imagecolorallocatealpha($img, 255, 255, 255, 65));

    // Initials: built-in font, scaled 2× ("pixel" style).
    $tw = 100;
    $th = 60;
    $tmp = imagecreatetruecolor($tw, $th);
    $black = imagecolorallocate($tmp, 0, 0, 0);
    imagefill($tmp, 0, 0, $black);
    $white = imagecolorallocate($tmp, 255, 255, 255);
    $px = (int) (($tw - strlen($initials) * 6) / 2);
    imagestring($tmp, 5, $px, (int) (($th - 16) / 2), $initials, $white);
    imagecolortransparent($tmp, $black);
    imagecopyresized($img, $tmp, (int) (($size - $tw * 2) / 2), (int) (($size - $th * 2) / 2), 0, 0, $tw * 2, $th * 2, $tw, $th);

    imagejpeg($img, $path, 85);
    imagedestroy($img);
    imagedestroy($tmp);
}

$palette = [
    [[233, 30, 99], [156, 39, 176]], [[63, 81, 181], [33, 150, 243]],
    [[0, 150, 136], [76, 175, 80]], [[255, 152, 0], [244, 67, 54]],
    [[121, 85, 72], [255, 87, 34]], [[96, 125, 139], [0, 188, 212]],
    [[106, 27, 154], [233, 30, 99]], [[27, 94, 32], [139, 195, 74]],
    [[25, 118, 210], [0, 188, 212]], [[230, 81, 0], [255, 202, 40]],
    [[69, 90, 100], [120, 144, 156]], [[136, 14, 79], [233, 30, 99]],
];

// ------------------------------------------------------------
// Orientation compatibility (identical to MatchingService)
// ------------------------------------------------------------
function covers(?string $orientation, ?string $ownGenre, ?string $targetGenre): bool
{
    if ($targetGenre === null || $targetGenre === 'autre' || $orientation === null || $orientation === 'bi') {
        return $targetGenre !== null;
    }
    if ($ownGenre === null || $ownGenre === 'autre') {
        return false;
    }
    return $orientation === 'homo' ? $ownGenre === $targetGenre : $ownGenre !== $targetGenre;
}

function orientationCompatible(array $a, array $b): bool
{
    return covers($a['orientation'], $a['genre'], $b['genre'])
        && covers($b['orientation'], $b['genre'], $a['genre']);
}

// ------------------------------------------------------------
// Profile generation
// ------------------------------------------------------------
$count = 520;
$passwordHash = password_hash('SeedPass123!', PASSWORD_DEFAULT);
$uploadDir = $settings['uploads']['dir'];

$users = []; // id => [genre, orientation]
$now = time();

echo "Generating $count profiles…\n";

for ($i = 0; $i < $count; $i++) {
    $genre = mt_rand(1, 100) <= 48 ? 'homme' : (mt_rand(1, 100) <= 92 ? 'femme' : 'autre');
    $o = mt_rand(1, 100);
    $orientation = $o <= 42 ? 'hetero' : ($o <= 58 ? 'homo' : ($o <= 83 ? 'bi' : null));

    $genderLabel = $genre === 'homme' ? 'male' : 'female';
    $prenom = $faker->firstName($genderLabel);
    $nom = $faker->lastName();
    $username = $faker->unique()->userName();

    [$villeNom, $villeLat, $villeLng] = $villes[array_rand($villes)];
    $lat = round($villeLat + (mt_rand(-200, 200) / 10000), 7);
    $lng = round($villeLng + (mt_rand(-200, 200) / 10000), 7);

    $birthdate = date('Y-m-d', mt_rand((int) ($now - 55 * 365.25 * 86400), (int) ($now - 18 * 365.25 * 86400)));

    $userId = $q->insert('users', [
        'email' => $faker->unique()->safeEmail(),
        'username' => $username,
        'nom' => $nom,
        'prenom' => $prenom,
        'password_hash' => $passwordHash,
        'genre' => $genre,
        'orientation' => $orientation,
        'bio' => mt_rand(1, 100) <= 85 ? $faker->realTextBetween(40, 220) : null,
        'birthdate' => $birthdate,
        'ville' => $villeNom,
        'lat' => $lat,
        'lng' => $lng,
        'gps_consent' => mt_rand(1, 100) <= 80 ? 1 : 0,
        'email_verifie' => 1,
        'actif' => 1,
        'derniere_connexion' => date('Y-m-d H:i:s', $now - mt_rand(0, 6 * 86400)),
    ]);
    $users[$userId] = ['genre' => $genre, 'orientation' => $orientation];

    // Profile photo (required: without it, likes are refused).
    [$c1, $c2] = $palette[array_rand($palette)];
    $initials = mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
    $photoName = 'seed_' . $userId . '.jpg';
    makeAvatar($uploadDir . '/' . $photoName, $initials, $c1, $c2);
    $q->insert('photos', [
        'user_id' => $userId,
        'path' => '/assets/uploads/' . $photoName,
        'is_profile' => 1,
        'position' => 0,
    ]);

    // 0–2 extra photos for ~30% of profiles.
    $extra = mt_rand(1, 100) <= 30 ? mt_rand(1, 2) : 0;
    for ($e = 1; $e <= $extra; $e++) {
        [$d1, $d2] = $palette[array_rand($palette)];
        $extraName = 'seed_' . $userId . '_' . $e . '.jpg';
        makeAvatar($uploadDir . '/' . $extraName, $initials, $d1, $d2);
        $q->insert('photos', [
            'user_id' => $userId,
            'path' => '/assets/uploads/' . $extraName,
            'is_profile' => 0,
            'position' => $e,
        ]);
    }

    // 3–6 tags per profile (array_rand returns keys).
    $keys = (array) array_rand($tagPool, mt_rand(3, 6));
    foreach ($keys as $key) {
        $tagName = $tagPool[$key];
        $q->run('INSERT IGNORE INTO user_tags (user_id, tag_id) VALUES (?, ?)', [$userId, $tagIds[$tagName]]);
    }

    if ($i % 100 === 0) {
        echo "  … $i profils\n";
    }
}

// ------------------------------------------------------------
// Interactions: compatible likes, visits, messages
// ------------------------------------------------------------
echo "Generating interactions…\n";

$ids = array_keys($users);
$likeCount = 0;
$matchCount = 0;

// Likes always orientation-compatible; ~40% reciprocity to produce
// real matches (demonstrable chat and notifications).
for ($i = 0; $i < (int) ($count * 1.6); $i++) {
    $a = $ids[array_rand($ids)];
    $b = $ids[array_rand($ids)];
    if ($a === $b || !orientationCompatible($users[$a], $users[$b])) {
        continue;
    }
    $already = $q->value('SELECT id FROM likes WHERE from_user_id = ? AND to_user_id = ?', [$a, $b]);
    if ($already === null) {
        $q->insert('likes', ['from_user_id' => $a, 'to_user_id' => $b]);
        $likeCount++;
    }
    if (mt_rand(1, 100) <= 40) {
        $already2 = $q->value('SELECT id FROM likes WHERE from_user_id = ? AND to_user_id = ?', [$b, $a]);
        if ($already2 === null) {
            $q->insert('likes', ['from_user_id' => $b, 'to_user_id' => $a]);
            $likeCount++;
            $matchCount++;
        }
    }
}

// Visits (one per pair, at most ~350).
$visitPairs = [];
for ($i = 0; $i < 500; $i++) {
    $visitor = $ids[array_rand($ids)];
    $visited = $ids[array_rand($ids)];
    if ($visitor === $visited || isset($visitPairs[$visitor . '-' . $visited])) {
        continue;
    }
    $visitPairs[$visitor . '-' . $visited] = true;
    $q->run(
        'INSERT IGNORE INTO visits (visitor_id, visited_id, viewed_at) VALUES (?, ?, ?)',
        [$visitor, $visited, date('Y-m-d H:i:s', $now - mt_rand(0, 5 * 86400))]
    );
}

// Messages between matches (both like directions exist).
$matchPairs = $q->fetchAll(
    'SELECT l1.from_user_id AS a, l1.to_user_id AS b FROM likes l1
     JOIN likes l2 ON l2.from_user_id = l1.to_user_id AND l2.to_user_id = l1.from_user_id
     ORDER BY RAND() LIMIT 100'
);
foreach ($matchPairs as $pair) {
    $a = (int) $pair['a'];
    $b = (int) $pair['b'];
    $nb = mt_rand(1, 3);
    for ($m = 0; $m < $nb; $m++) {
        $from = mt_rand(0, 1) === 0 ? $a : $b;
        $to = $from === $a ? $b : $a;
        $q->insert('messages', [
            'from_user_id' => $from,
            'to_user_id' => $to,
            'content' => $faker->realTextBetween(10, 120),
            'sent_at' => date('Y-m-d H:i:s', $now - mt_rand(60, 3 * 86400)),
        ]);
    }
}
$matchCount = count($matchPairs);

// Popularity recomputed (documented formula: likes + 2×matches − unlikes).
echo "Recomputing popularity…\n";
$popularity = new PopularityService($q);
foreach ($ids as $id) {
    $popularity->recompute($id);
}

// ------------------------------------------------------------
// Summary
// ------------------------------------------------------------
$totalUsers = (int) $q->value('SELECT COUNT(*) FROM users');
$totalPhotos = (int) $q->value('SELECT COUNT(*) FROM photos');
$totalLikes = (int) $q->value('SELECT COUNT(*) FROM likes');
$totalMessages = (int) $q->value('SELECT COUNT(*) FROM messages');
$totalTags = (int) $q->value('SELECT COUNT(*) FROM tags');

printf(
    "Done in %.1f s — %d profiles, %d photos, %d tags, %d likes (%d matches), %d messages.\n",
    microtime(true) - $start,
    $totalUsers,
    $totalPhotos,
    $totalTags,
    $totalLikes,
    $matchCount,
    $totalMessages
);
