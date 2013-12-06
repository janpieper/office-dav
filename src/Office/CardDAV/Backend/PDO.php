<?php

namespace Office\CardDAV\Backend;

use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Property\SupportedAddressData;
use Sabre\CardDAV\Backend\SyncSupport;

class PDO extends AbstractBackend implements SyncSupport {
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
   * @param string $principalUri
   * @return array
   */
  public function getAddressBooksForUser($principalUri) {
    $stmt = $this->pdo->prepare('SELECT id, uri, name, principal_uri, description, token FROM addressbooks WHERE principal_uri = ?');
    $stmt->execute([$principalUri]);

    $addressBooks = [];

    foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $addressBook) {
      $addressBooks[] = [
        'id' => $addressBook->id,
        'uri' => $addressBook->uri,
        'principaluri' => $addressBook->principal_uri,
        '{DAV:}displayname' => 'hello',
        '{urn:ietf:params:xml:ns:carddav}addressbook-description' => $addressBook->description,
        '{http://calendarserver.org/ns/}getctag' => $addressBook->token,
        '{urn:ietf:params:xml:ns:carddav}' => new SupportedAddressData,
        '{DAV:}sync-token' => $addressBook->token
      ];
    }

    return $addressBooks;
  }

  /**
   * @param string $principalUri
   * @param string $uri
   * @param array $properties
   * @return integer
   */
  public function createAddressBook($principalUri, $uri, array $properties) {
    //$this->addChange($addressBookiId, 1);
    throw new \Exception(__METHOD__ . ': not implemted!');
  }

  /**
   * @param integer $addressBookId
   * @param array $mutations
   * @return boolean
   */
  public function updateAddressBook($addressBookId, array $mutations) {
    $this->addChange($addressBookiId, 2);
    throw new \Exception(__METHOD__ . ': not implemted!');
  }

  /**
   * @param integer $addressBookId
   */
  public function deleteAddressBook($addressBookId) {
    $this->addChange($addressBookiId, 3);
    throw new \Exception(__METHOD__ . ': not implemted!');
  }

  /**
   * @param integer $addressBookId
   * @return array
   */
  public function getCards($addressBookId) {
    $stmt = $this->pdo->prepare('SELECT id, data AS carddata, uri, UNIX_TIMESTAMP(modified) AS lastmoditified FROM cards WHERE addressbook_id = ?');
    $stmt->execute([$addressBookId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @param integer $addressBookId
   * @param string $uri
   * @return array
   */
  public function getCard($addressBookId, $uri) {
    $stmt = $this->pdo->prepare('SELECT id, data AS carddata, uri, UNIX_TIMESTAMP(modified) AS lastmodified FROM cards WHERE addressbook_id = ? AND uri = ?');
    $stmt->execute([$addressBookId, $uri]);
    return $stmt->fetch(\PDO::FETCH_ASSOC);
  }

  /**
   * @param integer $addressBookId
   * @param string $uri
   * @param string $data
   * @return string
   */
  public function createCard($addressBookId, $uri, $data) {
    $stmt = $this->pdo->prepare('INSERT INTO cards SET addressbook_id = ?, uri = ?, data = ?, modified = NOW()');
    $stmt->execute([$addressBookId, $uri, $data]);
    $this->addChange($addressBookId, 1, $uri);
    return '"' . md5($data) . '"';
  }

  /**
   * @param integer $addressBookId
   * @param string $uri
   * @param string $data
   * @return string
   */
  public function updateCard($addressBookId, $uri, $data) {
    $stmt = $this->pdo->prepare('UPDATE cards SET data = ? WHERE addressbook_id = ? AND uri = ?');
    $stmt->execute([$data, $addressBookId, $uri]);
    $this->addChange($addressBookId, 2, $uri);
    return '"' . md5($data) . '"';
  }

  /**
   * @param integer $addressBookId
   * @param string $uri
   * @return boolean
   */
  public function deleteCard($addressBookId, $uri) {
    $stmt = $this->pdo->prepare('DELETE FROM cards WHERE addressbook_id = ? AND uri = ?');
    $stmt->execute([$addressBookId, $uri]);
    $this->addChange($addressBookId, 3, $uri);
    return $stmt->rowCount() === 1;
  }

  /**
   * @param integer $addressBookId
   * @param integer $token
   * @param integer $level
   * @param integer $limit
   * @return array
   */
  public function getChangesForAddressBook($addressBookId, $token, $level, $limit = null) {
    $stmt = $this->pdo->prepare('SELECT token FROM addressbooks WHERE id = ?');
    $stmt->execute([$addressBookId]);

    if (!$currentToken = $stmt->fetchColumn()) {
      return null;
    }

    if ($token) {
      $changes = $this->getAllChangesForAddressBookWithToken($addressBookId, $token, $currentToken);
    } else {
      $changes = $this->getAllChangesForAddressBook($addressBookId);
    }

    $report = [
      'syncToken' => $currentToken,
      'added' => [],
      'modified' => [],
      'deleted' => []
    ];

    foreach ($changes as $uri => $operation) {
      switch ($operation) {
        case 1: $report['added'][] = $uri; break;
        case 2: $report['modified'][] = $uri; break;
        case 3: $report['deleted'][] = $uri; break;
      }
    }

    return $report;
  }

  /**
   * @param integer $addressBookId
   * @return array
   */
  private function getAllChangesForAddressBook($addressBookId) {
    $stmt = $this->pdo->prepare('SELECT uri, 1 AS operation FROM cards WHERE addressbook_id = ?');
    $stmt->execute([$addressBookId]);

    $changes = [];
    while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
      $changes[$row->uri] = $row->operation;
    }

    return $changes;
  }

  /**
   * @param integer $addressBookId
   * @param integer $fromToken
   * @param integer $toToken
   */
  private function getAllChangesForAddressBookWithToken($addressBookId, $fromToken, $toToken) {
    $stmt = $this->pdo->prepare('SELECT uri, operation FROM addressbook_changes WHERE token >= ? AND token < ? AND addressbook_id = ? ORDER BY token');
    $stmt->execute([$fromToken, $toToken, $addressBookId]);

    $changes = [];
    while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
      $changes[$row->uri] = $row->operation;
    }

    return $changes;
  }

  /**
   * @param integer $addressBookId
   * @param integer $operation
   * @param string $uri
   * @return boolean
   */
  private function addChange($addressBookId, $operation, $uri = '') {
    $stmt = $this->pdo->prepare('INSERT INTO addressbook_changes (addressbook_id, token, uri, operation) SELECT id, token, ?, ? FROM addressbooks WHERE id = ?');
    $stmt->execute([$uri, $operation, $addressBookId]);

    $stmt = $this->pdo->prepare('UPDATE addressbooks SET token = token + 1 WHERE id = ?');
    return $stmt->execute([$addressBookId]);
  }
}
