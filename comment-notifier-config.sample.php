<?php
// TODO: bør skrivast om til å vere konstantar
$tenantId = "";
$clientId = "";
$clientSecret = "";
$teamId = ""; // Team der Planner-brett og Kanal å poste i er
$channelId = ""; // TODO: kanalen der det postast melding om nye kommentarar  // URL-decode?
$planId = ""; // ID-en til planner-brettet
$bucketId = ""; // Bucket = Kolonne/swimlane i Planner-brett
$scopes = "offline_access Tasks.ReadWrite ChannelMessage.Send"; // older: User.Read GroupMember.Read.All ChannelSettings.Read.All
// TODO: må redirectUri vere mogeleg å nå frå internett?
$redirectUri = '';

define('CACHEDIR', ''); // sti til mappe der cache kan lesast og skrivast
?>