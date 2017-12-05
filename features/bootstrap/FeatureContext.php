<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context
{
    protected $response = null;
    protected $username = null;
    protected $password = null;
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct($github_username, $github_password)
    {
      $this->username = $github_username;
      $this->password = $github_password;
    }
    /**
     * @Given I am an anonymous user
     */
    public function iAmAnAnonymousUser()
    {
        return true;
    }

    /**
     * @When I search for behat
     */
    public function iSearchForBehat()
    {
      $client = new GuzzleHttp\Client(['base_uri' => 'https://api.github.com']);
      $this->response = $client->get('/search/repositories?q=behat');

    }

    /**
     * @Then I get a result
     */
    public function iGetAResult()
    {
      //HTTP response code verification
        $response_code = $this->response->getStatusCode();
        if($response_code <> 200){
          throw new Exception("It didn't work. we expected a 200 response code but got a ".$response_code);
        }
        //payload verification
        $data = json_decode($this->response->getBody(), true);
        if($data['total_count']==0){
          throw new Exception("We found zero results!");
        }
    }
    public function iExpectAResponseCode($arg1)
    {
      $response_code = $this->response->getStatusCode();
      if($response_code <> $arg1){
        throw new Exception("It didn't work. We expected a $arg1 response code but got a".$response_code);
      }
    }
          /**
       * @Given I am an authenticated user
       */
    public function iAmAnAuthenticatedUser()
    {
        $this->client = new GuzzleHttp\Client([
          'base_uri' => 'https://api.github.com',
          'auth' => [$this->username, $this->password]
        ]);
        $this->response = $this->client->get('/');

        /*if(200 != $response->getStatusCode()){
        throw new Exception("Authentication didn't work!");
        }*/
        $this->iExpectAResponseCode(200);
    }

    /**
     * @Given I have the following repositories:
     */
    public function iHaveTheFollowingRepositories(TableNode $table)
    {
        $this->table = $table->getRows();
        array_shift($this->table);
        foreach ($this->table as $id => $row) {
          $this->table[$id]['name']=$row[0].'/'.$row[1];
          $this->response = $this->client->get('/repos/'.$row[0].'/'.$row[1]);
          $this->iExpectAResponseCode(200);
        }
    }

    /**
     * @When I watch each repository
     */
    public function iWatchEachRepository()
    {
        $parameters = json_encode(['subscribed' => 'true']);
        foreach($this->table as $row) {
          $watch_url = '/repos/'.$row['name'].'/subscription';
          $this->client->put($watch_url, ['body' => $parameters]);
        }
    }

    /**
     * @Then My watch list will include those repositories
     */
     public function myWatchListWillIncludeThoseRepositories()
     {
        $watch_url = '/users/'.$this->username.'/subscriptions';
        $this->response = $this->client->get($watch_url);
        $watches = $this->getBodyAsJson();

        foreach($this->table as $row){
          $fullname = $row['name'];

          foreach($watches as $watch){
            if($fullname == $watch['full_name']){
              break 2;
            }
          }
          throw new Exception("Error! ".$this->username." is not watching ".$fullname);
        }
    }
    protected function getBodyAsJson(){
      return json_decode($this->response->getBody(), true);
    }

}
