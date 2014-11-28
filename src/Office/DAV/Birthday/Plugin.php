<?php

namespace Office\DAV\Birthday;

use Sabre\DAV\UUIDUtil;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\INode;
use Sabre\DAV\StringUtil;
use Sabre\VObject;
use Sabre\CardDAV\AddressBook;

class Plugin extends ServerPlugin {
  /**
   * @var Sabre\DAV\Server
   */
  private $server = null;

  /**
   * @param Sabre\DAV\Server $server
   */
  public function initialize(Server $server) {
    $server->on('beforeCreateFile', [$this, 'createBirthdayAppointment']);
    $server->on('afterWriteContent', [$this, 'updateBirthdayAppointment']);
    $server->on('beforeUnbind', [$this, 'deleteBirthdayAppointment']);

    $this->server = $server;
  }

  /**
   * @return array
   */
  public function getFeatures() {
    return ['auto-birthday-appointment']; // TODO: find better naming
  }

  /**
   * @param string $uri
   * @param Sabre\DAV\INode $node
   * @return boolean
   */
  public function createBirthdayAppointment($uri, &$data, INode $node, &$modified) {
    if (!$node instanceof AddressBook) {
      return true;
    }

    if (is_resource($data)) {
      $data = stream_get_contents($data);
    }

    if (md5($data) !== md5($data = StringUtil::ensureUTF8($data))) {
      $modified = true;
    }

    // Example: 'addresbooks/jan/default/24bd27b4-7316-11e4-28d2447013e6.vcf'
    if (!preg_match('/addressbooks\/(.+)\/(.+)\/.+\..+$/', $uri, $matches)) {
      return true;
    }

    $account = $matches[1];
    $folder = $matches[2];

    if (($date = $this->extractBirthday($data)) !== null) {
      $event_uri = vsprintf('calendars/%s/%s/%s.ics', array(
        $account, $folder, UUIDUtil::getUUID()
      ));

      $event_data = $this->generateRecurringEvent(
        'Jan Pieper', // TODO: extract name from contact
        $date
      );

      $this->server->createFile($event_uri, $event_data);
    }

    return true;
  }

  /**
   * @param string $uri
   * @param Sabre\DAV\INode $node
   * @return void
   */
  public function updateBirthdayAppointment($uri, INode $node) {
    //var_dump($this->extractBirthday($node->get()));
    return true;
  }

  /**
   * @param string $uri
   */
  public function deleteBirthdayAppointment($uri) {
    //var_dump($uri);
    return true;
  }

  /**
   * @param string $data
   * @return DateTime
   */
  private function extractBirthday($data) {
    $vobj = VObject\Reader::read($data);
    $date = null;

    foreach ($vobj->children() as $child) {
      if ($child->name !== 'BDAY') {
        continue;
      }

      $date = $child->getValue();
      if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches) &&
          !preg_match('/^(\d{4})(\d{2})(\d{2})$/', $date, $matches)) {
        continue;
      }

      $date = new \DateTime(vsprintf('%d-%d-%d', array(
        $matches[1],                  // year
        str_pad($matches[2], 2, '0'), // month
        str_pad($matches[3], 2, '0')  // day
      )));
    }

    return $date;
  }

  /**
   * @
   * @return string
   */
  private function generateRecurringEvent($person, \DateTime $birthday) {
    return implode(PHP_EOL, array(
      'BEGIN:VCALENDAR',
        'VERSION: 2.0',
        'CALSCALE:GREGORIAN',
        'PRODID:-//Private//OfficeDAV/EN',
        'BEGIN:VTIMEZONE',
          'TZID:Europe/Amsterdam',
          'BEGIN:STANDARD',
            'DTSTART:20121028T030000',
            'RRULE:FREQ=YEARLY;BYDAY=4SU,BYMONTH=10',
            'TZNAME:CET',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
          'END:STANDARD',
          'BEGIN:DAYLIGHT',
            'DTSTART:20130331T020000',
            'RRULE:FREQ=YEARLY;BYDAY=5SU,BYMONTH=3',
            'TZNAME:CEST',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
          'END:DAYLIGHT',
        'END:VTIMEZONE',
        'BEGIN:VEVENT',
          'UID:' . UUIDUtil::getUUID(),
          'DTSTART;VALUE=DATE:' . $birthday->format('Ymd'),
          'DTEND;VALUE=DATE:' . $birthday->format('Ymd'),
          'CLASS:PRIVATE',
          'CREATED:' . date('YmdTHis\Z'), // TODO: UTC?
          'DTSTAMP:' . date('YmdTHis\Z'), // TODO: UTC?
          sprintf(
            'RRULE:FREQ=YEARLY;BYMONTH=%d;INTERVAL=1;BYMONTHDAY=%d',
            $birthday->format('m'), $birthday->format('d')
          ),
          'SEQUENCE:0',
          'SUMMARY:Geburtstag (' . $person . ')',
          'TRANSP:OPAQUE',
          'X-MICROSOFT-CDO-ALLDAYEVENT:TRUE',
        'END:VEVENT',
      'END:VCALENDAR'
    ));
  }
}
