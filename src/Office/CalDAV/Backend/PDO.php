<?php

namespace Office\CalDAV\Backend;

use Sabre\CalDAV\Backend\AbstractBackend;
use Sabre\CalDAV\Backend\SyncSupport;
use Sabre\CalDAV\Backend\SubscriptionSupport;
use Sabre\CalDAV\Property\SupportedCalendarComponentSet;
use Sabre\CalDAV\Property\ScheduleCalendarTransp;
use Sabre\VObject\Reader as VObjectReader;

class PDO extends AbstractBackend implements SyncSupport {
  /**
   * @param PD
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
  public function getCalendarsForUser($principalUri) {
    $stmt = $this->pdo->prepare('SELECT id, uri, token, name, timezone, description, components, principal_uri, transparent FROM calendars WHERE principal_uri = ?');
    $stmt->execute([$principalUri]);

    $calendars = [];

    foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $row) {
      $components = [];
      if (!empty($row->components)) {
        $components = explode(',', $row->components);
      }

      $calendars[] = [
        'id' => $row->id,
        'uri' => $row->uri,
        'principaluri' => $row->principal_uri,
        '{http://calendarserver.org/ns/}getctag' => 'http://sabredav.org/ns/sync/' . ($row->token ?: 0),
        '{DAV:}sync-token' => $row->token ?: 0,
        '{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set' => new SupportedCalendarComponentSet($components),
        '{urn:ietf:params:xml:ns:caldav}schedule-calendar-transp' => new ScheduleCalendarTransp($row->transparent ? 'transparent' : 'opaque'),
        '{DAV:}displayname' => $row->name,
        '{urn:ietf:params:xml:ns:caldav}calendar-description' => $row->description,
        '{urn:ietf:params:xml:ns:caldav}calendar-timezone' => $row->timezone
      ];
    }

    return $calendars;
  }

  /**
   * @param string $principalUri
   * @param string $uri
   * @param array $properties
   * @return TODO
   */
  public function createCalendar($principalUri, $uri, array $properties) {
    //$this->addChange($calendarId, 1);
    throw new \Exception(__METHOD__ . ': not implemented!');
  }

  /**
   * @param integer $calendarId
   * @return boolean
   */
  public function deleteCalendar($calendarId) {
    throw new \Exception(__METHOD__ . ': not implemented!');
  }

  /**
   * @param integer $calendarId
   * @return array
   */
  public function getCalendarObjects($calendarId) {
    $stmt = $this->pdo->prepare('SELECT id, uri, CONCAT(\'"\', MD5(data), \'"\') AS etag, calendar_id AS calendarid, LENGTH(data) AS size, LOWER(component) AS component, UNIX_TIMESTAMP(modified) AS lastmodified FROM calendar_objects WHERE calendar_id = ?');
    $stmt->execute([$calendarId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
  }

  /**
   * @param integer $calendarId
   * @param string $uri
   * @return array
   */
  public function getCalendarObject($calendarId, $uri) {
    $stmt = $this->pdo->prepare('SELECT id, uri, CONCAT(\'"\', MD5(data), \'"\') AS etag, calendar_id AS calendarid, LENGTH(data) AS size, LOWER(component) AS component, data AS calendardata, UNIX_TIMESTAMP(modified) AS lastmodified FROM calendar_objects WHERE calendar_id = ? AND uri = ?');
    $stmt->execute([$calendarId, $uri]);
    return $stmt->fetch(\PDO::FETCH_ASSOC);
  }

  /**
   * @param integer $calendarId
   * @param string $uri
   * @param string $data
   * @return string
   */
  public function createCalendarObject($calendarId, $uri, $data) {
    $componentType = $this->getComponentTypeByCalendarObject($data);

    $stmt = $this->pdo->prepare('INSERT INTO calendar_objects SET calendar_id = ?, uri = ?, data = ?, component = ?, modified = NOW()');
    $stmt->execute([$calendarId, $uri, $data, $componentType]);
    $this->addChange($calendarId, 1, $uri);

    return '"' . md5($data) . '"';
  }

  /**
   * @param integer $calendarId
   * @param string $uri
   * @param string $data
   * @return string
   */
  public function updateCalendarObject($calendarId, $uri, $data) {
    $componentType = $this->getComponentTypeByCalendarObject($data);

    $stmt = $this->pdo->prepare('UPDATE calendar_objects SET data = ?, component = ? WHERE calendar_id = ? AND uri = ?');
    $stmt->execute([$data, $componentType, $calendarId, $uri]);
    $this->addChange($calendarId, 2, $uri);

    return '"' . md5($data) . '"';
  }

  /**
   * @param integer $calendarId
   * @param string $uri
   * @return boolean
   */
  public function deleteCalendarObject($calendarId, $uri) {
    $this->addChange($calendarId, 3, $uri);
    $stmt = $this->pdo->prepare('DELETE FROM calendar_objects WHERE calendar_id = ? AND uri = ?');
    return $stmt->execute([$calendarId, $uri]);
  }

  /**
   * @param integer $calendarId
   * @param integer $token
   * @param integer $level
   * @param integer $limit
   * @return array
   */
  public function getChangesForCalendar($calendarId, $token, $level, $limit = null) {
    $stmt = $this->pdo->prepare('SELECT token FROM calendars WHERE id = ?');
    $stmt->execute([$calendarId]);

    if (!$currentToken = $stmt->fetchColumn()) {
      return null;
    }

    if ($token) {
      $changes = $this->getAllChangesForCalendarWithToken($calendarId, $token, $currentToken);
    } else {
      $changes = $this->getAllChangesForCalendar($calendarId);
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
   * @param integer $calendarId
   * @return array
   */
  private function getAllChangesForCalendar($calendarId) {
    $stmt = $this->pdo->prepare('SELECT uri, 1 AS operation FROM calendar_objects WHERE calendar_id = ?');
    $stmt->execute([$calendarId]);

    $changes = [];
    while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
      $changes[$row->uri] = $row->operation;
    }

    return $changes;
  }

  /**
   * @param integer $calendarId
   * @param integer $fromToken
   * @param integer $toToken
   */
  private function getAllChangesForCalendarWithToken($calendarId, $fromToken, $toToken) {
    $stmt = $this->pdo->prepare('SELECT uri, operation FROM calendar_changes WHERE token >= ? AND token < ? AND calendar_id = ? ORDER BY token');
    $stmt->execute([$fromToken, $toToken, $calendarId]);

    $changes = [];
    while ($row = $stmt->fetch(\PDO::FETCH_OBJ)) {
      $changes[$row->uri] = $row->operation;
    }

    return $changes;
  }

  /**
   * @param integer $calendarId
   * @param integer $operation
   * @param string $uri
   */
  private function addChange($calendarId, $operation, $uri = '') {
    $stmt = $this->pdo->prepare('INSERT INTO calendar_changes (uri, calendar_id, operation, token) SELECT ?, ?, ?, token FROM calendars WHERE id = ?');
    $stmt->execute([$uri, $calendarId, $operation, $calendarId]);

    $stmt = $this->pdo->prepare('UPDATE calendars SET token = token + 1 WHERE id = ?');
    return $stmt->execute([$calendarId]);
  }

  /**
   * @param string $data
   * @return string
   */
  private function getComponentTypeByCalendarObject($data) {
    foreach (VObjectReader::read($data)->getComponents() as $component) {
      if ($component->name !== 'VTIMEZONE') {
        return $component->name;
      }
    }
    throw new \Exception('Calendar objects must have a VJOURNAL, VEVENT or VTODO component.');
  }
}
