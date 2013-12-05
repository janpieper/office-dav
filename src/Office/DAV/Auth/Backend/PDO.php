<?php

namespace Office\DAV\Auth\Backend;

use Sabre\DAV\Auth\Backend\AbstractBasic;

class PDO extends AbstractBasic {
  /**
   * @var PDO
   */
  private $pdo = null;

  /**
   * @var string
   */
  private $realm = null;

  /**
   * @param PDO $pdo
   * @param string $realm
   */
  public function __construct(\PDO $pdo, $realm) {
    $this->pdo = $pdo;
    $this->realm = $realm;
  }

  /**
   * @param string $username
   * @param string $password
   * @return boolean
   */
  protected function validateUserPass($username, $password) {
    $stmt = $this->pdo->prepare('SELECT digest FROM users WHERE name = ?');
    $stmt->execute([$username]);
    $digest = md5(implode(':', [$username, $this->realm, $password]));
    return $stmt->fetchColumn() === $digest;
  }
}
