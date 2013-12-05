<?php

namespace Office\DAVACL\PrincipalBackend;

use Sabre\DAVACL\PrincipalBackend\AbstractBackend;

class PDO extends AbstractBackend {
  /**
   * @var PDO
   */
  private $pdo = null;

  /**
   * @param PDO $pdo
   */
  public function __construct(\PDO $pdo) {
    $this->pdo = $pdo;
  }

  /**
   * @param string $prefix
   * @return array
   */
  public function getPrincipalsByPrefix($prefix) {
    throw new \Exception(__METHOD__ . ': not implemted!');
  }

  /**
   * @param string $path
   * @return array
   */
  public function getPrincipalByPath($path) {
    $stmt = $this->pdo->prepare('SELECT id, uri, name, email FROM principals WHERE uri = ?');
    $stmt->execute([$path]);

    if (!$row = $stmt->fetchObject()) {
      return null;
    }

    return [
      'id' => $row->id,
      'uri' => $row->uri,
      '{DAV:}displayname' => $row->name,
      '{http://sabredav.org/ns}email-address' => $row->email
    ];
  }

  /**
   * @param string $path
   * @param array $mutations
   * @return array|boolean
   */
  public function updatePrincipal($path, $mutations) {
    throw new \Exception(__METHOD__ . ': not implemted!');
  }

  /**
   * @param string $prefix
   * @param array $properties
   * @return array
   */
  public function searchPrincipals($prefix, array $properties) {
    throw new \Exception(__METHOD__ . ': not implemted!');
  }

  /**
   * @param string $principal
   * @return array
   */
  public function getGroupMemberSet($principal) {
    throw new \Exception(__METHOD__ . ': not implemted!');
  }

  /**
   * @param string $principal
   * @return array
   */
  public function getGroupMembership($principal) {
    return [];
  }

  /**
   * @param string $principal
   * @param array $members
   * @return boolean
   */
  public function setGroupMemberSet($principal, array $members) {
    throw new \Exception(__METHOD__ . ': not implemted!');
  }
}
