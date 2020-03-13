<?php
namespace SirFaenor\MailupHttpClient\Tests;
use PHPUnit\Framework\TestCase;
use SirFaenor\MailupHttpClient\Client;
use Exception;


/**
 * Test di 
 * iscrizione
 * verifica stato
 * aggiornamento
 * disiscrizione
 * Personalizzare $consoleUrl, $csvMap, $listId e $listGuid
 * 
 */
class MailupTest extends TestCase
{
    

    /**
     * @var string
     * Url della console mailup.
     * Qualcosa come "https://xxx.xxxxxxx.com"
     */
    protected $consoleUrl = 'https://xxxxx.xxxxxx.com';


    /**
     * @var array
     * Mappatura dei campi su Mailup
     */
    protected $csvMap = [
        "campo1" =>  "nome"
        ,"campo2" =>  "cognome"
        ,"campo3" =>  "r.soc. - fax"
        ,"campo4" =>  "citta"
        ,"campo5" =>  "provincia"
        ,"campo6" =>  "cap"
        ,"campo7" =>  "indirizzo"
        ,"campo8" =>  "paese"
        ,"campo9" =>  "telefono"
        ,"campo10" =>  "professione"
        ,"campo11" =>  "cellulare"
        ,"campo12" =>  "link_attivazione"
        ,"campo13" =>  "data_nascita"
    ];

    
    /**
     * @var int 
     * Id della lista
     */
    protected $listId = null;


    /**
     * @var string
     * Codice alfanumerico della lista come da "tabella codici"
     * Qualcosa come xxxxxxx--xxxx-xxxx-xxxxxx
     */
    protected $listGuid = 'xxxxxxx--xxxx-xxxx-xxxxxx';


    /**
     * @var int
     * Gruppo di default come da "tabella codici".
     * Il gruppo può essere passato a runtime a ciascun metodo in cui è richiesto.
     */
    protected $testGroup = null;


    /**
     * @var string
     * Email di test
     */
    protected $testEmail = '';

    
    /**
     * @var string
     * Nome di test
     */
    protected $testName = '';

    
    /**
     * @var string
     * Cognome di test
     */
    protected $testSurname = '';

    

    /**
     * Iscrizione
     *
     * @return void
     */
    public function testSubscribe()
    {
        

        $client = new Client($this->consoleUrl, $this->csvMap);

        $groupId = $this->testGroup;

        $data = [
            "nome" => $this->testName,
            "cognome" => $this->testSurname,
        ];

        $optIn = 0;

        try {

            //true se tutto va bene
            $response = $client->subscribe($this->testEmail, $this->listId, $groupId, $data, $optIn);
           
            $this->assertTrue($response);

        } catch (Exception $e) {

            
            // 3 per destinatario esistente, segnalo solo gli altri errori
            if ($client->getLastError() != 3) {
                echo("Errore iscrizione newsletter: ".$client->getLastError().' - '.$e->getMessage());
            } 

            $this->fail();
        }

    }


    /**
     * Controllo iscrizione
     *
     * @return void
     */
    public function testCheckSubscription()
    {
        
        $client = new Client($this->consoleUrl, $this->csvMap);

        $response = $client->checkSubscription($this->testEmail, $this->listId, $this->listGuid);
            
        $this->assertEquals($response, "subscribed");

    }


    /**
     * Aggiornamento
     *
     * @return void
     */
    public function testUpdate()
    {
        
        $client = new Client($this->consoleUrl, $this->csvMap);

        $groupId = $this->testGroup;

        $data = [
            "nome" => $this->testName." - edited",
            "cognome" => $this->testSurname." -edited",
        ];

        $response = $client->update($this->testEmail, $this->listId, $this->listGuid, $data);
            
        $this->assertTrue($response);
       
    }

    
    /**
     * Disiscrizione
     *
     * @return void
     */
    public function testUnsubscribe()
    {
        
        $client = new Client($this->consoleUrl, $this->csvMap);

        $response = $client->unsubscribe($this->testEmail, $this->listId, $this->listGuid);
            
        $this->assertTrue($response);

        $response = $client->checkSubscription($this->testEmail, $this->listId, $this->listGuid);
            
        $this->assertEquals($response, "unsubscribed");

       
    }

    



}
