<?php

class LinkManager
{
    private string $chars;

    private SQLite3 $db;

    public function __construct(?string $dbPath = null, ?string $allowedChars = null)
    {
        $this->db    = new SQLite3($dbPath ?? __DIR__ . '/links.db');
        $this->chars = $allowedChars ?? 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        $this->initDb();
    }

    /**
     * Initialize default data.
     *
     * @return void
     */
    private function initDb(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL,
            clicks INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
    }


    /**
     * Encode the ID to a string based on the allowed characters.
     *
     * @param int $id
     *
     * @return string
     */
    private function encodeId(int $id): string
    {
        $length = strlen($this->chars);
        $code   = '';

        while ($id > 0) {
            $code .= $this->chars[$id % $length];
            $id   = (int) ($id / $length);
        }

        return $code;
    }

    /**
     * Decode the string to an ID.
     *
     * @param string $str
     *
     * @return int
     */
    private function decodeId(string $str): int
    {
        $length = strlen($this->chars);
        $id     = 0;

        for ($i = 0; $i < strlen($str); $i++) {
            $id += strpos($this->chars, $str[$i]) * pow($length, $i);
        }

        return $id;
    }

    /**
     * Store the URL and return the encoded ID.
     *
     * @param string $url
     *
     * @return string
     */
    public function store(string $url): string
    {
        $query = $this->db->prepare("INSERT INTO links (url) VALUES (:url)");
        $query->bindValue(':url', $url);
        $query->execute();

        // Return the encoded id.
        return $this->encodeId($this->db->lastInsertRowID());
    }

    /**
     * Find the URL by the encoded ID.
     *
     * @param string $str
     *
     * @return string|null
     */
    public function find(string $str): ?string
    {
        $id     = $this->decodeId($str);
        $search = $this->db->prepare("SELECT url FROM links WHERE id = :id");
        $search->bindValue(':id', $id, SQLITE3_INTEGER);
        $result = $search->execute()->fetchArray(SQLITE3_ASSOC);

        // we can't find the link.
        if (!$result) {
            return null;
        }

        // Increase the click count and update the updated_at column.
        $update = $this->db->prepare("UPDATE links SET clicks = clicks + 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $update->bindValue(':id', $id, SQLITE3_INTEGER);
        $update->execute();

        return $result['url'];
    }
}

$manager = new LinkManager();

$path = 'http://link.test';
if (!empty($_REQUEST['url']) && filter_var($_REQUEST['url'], FILTER_VALIDATE_URL)) {
    $code = $manager->store($_GET['url']);

    echo "Your Link is: $path/$code";

    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'])['path'];

if ($path === '/') {
    echo '<form method="get">
        <input type="text" name="url" placeholder="Enter your URL">
        <button type="submit">Shorten</button>
    </form>';

    exit;
}

$url = $manager->find(substr($path, 1));

header('Location: ' . $url);

