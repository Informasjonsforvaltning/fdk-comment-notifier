<?php
ini_set('log_errors', 1);
ini_set('display_errors', 0);

require_once('comment-notifier-config.php');

require_once 'JG_Cache.php';
$cache = new JG_Cache(CACHEDIR);

require __DIR__ . '/vendor/autoload.php';

$cacheKeyAccessToken = "mstestAccessToken";
$cacheKeyOAuthState = "msTestOAuthState";


/*  FLYT
if (?code er satt) {

} else if (access token lagra) {
    if (lagra-token er gyldig) {
        ... køyr
    } else {
        start autorisering
    }
} else {

}

Gjer forespørslar
*/

use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;


function printToken(AccessToken $accessToken) {
    echo 'Access Token: ' . $accessToken->getToken() . "<br><br>";
    echo 'Refresh Token: ' . $accessToken->getRefreshToken() . "<br><br>";
    echo 'Expires in: ' . $accessToken->getExpires() . "<br><br>";
    echo 'Values: ' . var_export($accessToken->getValues(), true) . "<br><br>";
    echo 'Already expired? ' . ($accessToken->hasExpired() ? 'expired' : 'not expired') . "<br><br>";
}

function printGraphResponse($graphResponse) {
    print("<pre>\n");
    var_export($graphResponse);
    print("</pre><br/>\n");
}

function printLinkToSelf($linkText = "Tilbake") {
    print('<a href="' . basename(__FILE__) . '">' . $linkText . '</a>' . "<br/>\n");
}

// Kode for å OAuth - hente brukar-token
// kode tilpassa frå https://github.com/TheNetworg/oauth2-azure
$provider = new \League\OAuth2\Client\Provider\GenericProvider([
    'clientId'                => $clientId,    // The client ID assigned to you by the provider
    'clientSecret'            => $clientSecret,    // The client password assigned to you by the provider
    'redirectUri'             => $redirectUri,
    'urlAuthorize'            => 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/authorize',
    'urlAccessToken'          => 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token',
    'urlResourceOwnerDetails' => 'https://graph.microsoft.com/',
    'scopes'                  => array($scopes)
]);

// Hentar ut lagra accecss token dersom den finst. Sjekkar at den ikkje er utløpt
print("Sjekkar om access token er lagra frå før av.<br/>\n");

/** @var AccessToken $accessToken */
$accessToken = $cache->get($cacheKeyAccessToken);

if ($accessToken === false) {
    print("Nei. Access token er ikkje sett frå før av.<br/><br/>\n\n");
} else {
    print("Ja. Access token er sett frå før av.<br/><br/>\n");
    printToken($accessToken);
    
    // Sjekk om token er utløpt
    print("Er access token utløpt?<br/>\n");
    print($accessToken->hasExpired() ? "Ja" : "Nei");
    print("<br/><br/>\n");

    if ($accessToken->hasExpired()) {
        $cache->clear($cacheKeyAccessToken);
        $accessToken = false;
        printLinkToSelf();
    }

    // Sjekk om token ein har inneheld rette scopes
    $accessTokenValues = $accessToken->getValues();
    if (isset($accessTokenValues["scope"])) {
        $neededScopes = explode(" ", $scopes);
        $existingScopes = explode(" ", $accessTokenValues["scope"]);
        foreach ($neededScopes as $neededScope) {
            if ($neededScope == "offline_access") continue;
            $hasScope = false;
            foreach ($existingScopes as $existingScope) {
                if ($neededScope == $existingScope) $hasScope = true;
            }
            if ($hasScope === false) {
                print("Eksisterande access token manglar scope: " . $neededScope . "<br/>\n");
                print("Fjernar lagra access token.<br/>\n");
                $cache->clear($cacheKeyAccessToken);
                printLinkToSelf();                
                die();
            }
        }
    }
}

$storedState = $cache->get($cacheKeyOAuthState);

// Dersom vi ikkje har gyldig (ikkje utløpt) access token frå før, og vi ikkje har autoriseringskode - få autoriseringskode
if (!isset($_GET['code']) && $accessToken === false) {
    // Fetch the authorization URL from the provider; this returns the
    // urlAuthorize option and generates and applies any necessary parameters
    // (e.g. state).
    $authorizationUrl = $provider->getAuthorizationUrl();

    // Get the state generated for you and store it to the session.
    // $_SESSION['oauth2state'] = $provider->getState();
    $cache->set($cacheKeyOAuthState, $provider->getState());

    // Redirect the user to the authorization URL.
    header('Location: ' . $authorizationUrl);
    exit;

// Check given state against previously stored one to mitigate CSRF attack
// } elseif (empty($_GET['state']) || (isset($_SESSION['oauth2state']) && $_GET['state'] !== $_SESSION['oauth2state'])) {
} elseif (empty($_GET['state']) || ($storedState !== false && $_GET['state'] !== $storedState)) {

    // if (isset($_SESSION['oauth2state'])) {
    if ($storedState !== false) {
        // unset($_SESSION['oauth2state']);
        $cache->clear($cacheKeyOAuthState);
    }

    exit('Invalid state');

// Dersom gyldig access token ikkje finst - hent access token via auth-code
// } else {
} else if ($accessToken === false) {
    try {
        // Try to get an access token using the authorization code grant.
        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $_GET['code']
        ]);

        // We have an access token, which we may use in authenticated
        // requests against the service provider's API.
        printToken($accessToken);
        $cache->set($cacheKeyAccessToken, $accessToken);

        // print(serialize($accessToken)); // serialisering ser ok ut

        // ... fjerna diverse eksempelkode ...

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {

        // Failed to get the access token or user details.
        print($e->getMessage());
        print("<br/>\n");
        print('<a href="' . basename(__FILE__) . '">Tilbake</a>');
        exit();

    }

}

print("Prøver å hente data frå Graph<br/>\n");
$graph = new Graph();
$graph->setAccessToken($accessToken->getToken());

try {
    // Hent liste over alle grupper
    // https://docs.microsoft.com/en-us/graph/teams-list-all-teams
    // https://docs.microsoft.com/en-us/graph/api/group-list?view=graph-rest-beta&preserve-view=true&tabs=http
    /*
    $groups = $graph->createRequest("GET", "/groups")
    // ->setReturnType(Model\User::class)
    ->execute();
    printGraphResponse($groups);
    */

    // List alle kanalar i eit Team
    // $channels = $graph->createRequest("GET", "/teams/" . $teamId . "/channels")->execute();
    // printGraphResponse($channels);

    // Hent alle Planner-brett for eit gitt Team
    // $planners = $graph->createRequest("GET", "/groups/" . $teamId . "/planner/plans")->execute();
    // printGraphResponse($planners);
    
    // Hent alle buckets i ein gitt Plan
    // $planBuckets = $graph->createRequest("GET", "/planner/plans/" . $planId . "/buckets")->execute();
    // printGraphResponse($planBuckets);

    // Hent tasks i gitt Plan
    // $tasks = $graph->createRequest("GET", "/planner/plans/" . $planId . "/tasks")->execute();
    // printGraphResponse($tasks);
    
    // Opprett Task i gitt Plan og Bucket
    $data = [
        "planId" => $planId,
        "bucketId" => $bucketId,
        "title" => "test2"
    ];
    /** @var GraphResponse $createTaskResponse */
    $createTaskResponse = $graph->createRequest("POST", "/planner/tasks")
                        ->attachBody($data)
                        ->execute();
    printGraphResponse($createTaskResponse);

    // Hent ut ID-en til Task som nettopp vart oppretta
    $taskId = $createTaskResponse->getBody()["id"];
    // print("Task id: " . $taskId . "<br/>\n");

    // Hent Task details
    $taskDetails = $graph->createRequest("GET", "/planner/tasks/" . $taskId . "/details")->execute();
    printGraphResponse($taskDetails);

    // Oppdatere Task details
    $etag = $taskDetails->getHeaders()["ETag"];
    $data = ["description" => "Her er beskrivinga!", "previewType" => "description"];
    $updateTaskDetailsResponse = $graph->createRequest("PATCH", "/planner/tasks/" . $taskId . "/details")
                        ->attachBody($data)
                        ->addHeaders(array("If-Match" => $etag))
                        ->execute();
    printGraphResponse($updateTaskDetailsResponse);

    // TODO: legg inn faktiske kommentarar som er komne inn

    // TODO: post i Teams-kanal om at det er komen ny kommentar
    $data = [
        "body" => array(
            "content" => "Det er komen ein ny kommentar!"
        )
    ];
    /** @var GraphResponse $createTaskResponse */
    $sendChannelMessageResponse = $graph->createRequest("POST", "/teams/" . $teamId . "/channels/" . $channelId . "/messages")
                        ->attachBody($data)
                        ->execute();
    printGraphResponse($sendChannelMessageResponse);
    

} catch (\GuzzleHttp\Exception\ClientException $e) {
    print("FEIL! Response body:\n");
    print($e->getResponse()->getBody()->getContents());
    throw($e);
}


die("Ser ut til at ting er ok så langt.");

// Hente token som applikasjon
$guzzle = new \GuzzleHttp\Client();
$url = 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/token?api-version=1.0';
try {
    $response = $guzzle->post($url, [
        'form_params' => [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'resource' => 'https://graph.microsoft.com/',
            'grant_type' => 'client_credentials',
        ],
        ]);
} catch (\GuzzleHttp\Exception\ClientException $e) {
    print("FEIL! Response body:\n");
    print($e->getResponse()->getBody()->getContents());
    throw($e);
}

$token = json_decode($response->getBody()->getContents());

$accessToken = $token->access_token;
  
echo "AccessToken: " . $accessToken . "\n";

$graph = new Graph();
$graph->setAccessToken($accessToken);

// List ut alle grupper.
// Grupper som har "Team" under "resourceProvisioningOptions" er Team-teams
// Måtte ha Directory.ReadAll for å få gjere kall til /groups
/*
$groups = $graph->createRequest("GET", "/groups")
                // ->setReturnType(Model\User::class)
                ->execute();

print_r($groups);
*/

// List alle kanalar i eit Team
// $channels = $graph->createRequest("GET", "/teams/" . $teamId . "/channels")->execute();
// print_r($channels);

// List alle planner-brett i gitt teamId/groupId
$planners = $graph->createRequest("GET", "/groups/" . $teamId . "/planner/plans")->execute();
print_r($planners);

?>