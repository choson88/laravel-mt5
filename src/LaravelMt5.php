<?php


namespace Tarikhagustia\LaravelMt5;


use Tarikhagustia\LaravelMt5\Entities\Trade;
use Tarikhagustia\LaravelMt5\Entities\User;
use Tarikhagustia\LaravelMt5\Exceptions\ConnectionException;
use Tarikhagustia\LaravelMt5\Exceptions\TradeException;
use Tarikhagustia\LaravelMt5\Exceptions\UserException;
use Tarikhagustia\LaravelMt5\Lib\MTAuthProtocol;
use Tarikhagustia\LaravelMt5\Lib\MTConnect;
use Tarikhagustia\LaravelMt5\Lib\MTLogger;
use Tarikhagustia\LaravelMt5\Lib\MTRetCode;
use Tarikhagustia\LaravelMt5\Lib\MTTradeProtocol;
use Tarikhagustia\LaravelMt5\Lib\MTUser;
use Tarikhagustia\LaravelMt5\Lib\MTUserProtocol;

//+------------------------------------------------------------------+
//--- web api version
define("WebAPIVersion", 2190);
//--- web api date
define("WebAPIDate", "18 Oct 2019");

class LaravelMt5
{
    /**
     * @var MTConnect $m_connect
     */
    protected $m_connect;
    //--- name agent
    private $m_agent = 'WebAPI';
    //--- is set crypt connection
    private $m_is_crypt = true;

    public function __construct($agent = "WebAPI", $is_crypt = true)
    {
        $file_path = storage_path('logs/');
        $this->m_agent = $agent;
        $this->m_is_crypt = $is_crypt;
        MTLogger::Init($agent, config('app.debug'), $file_path);
    }

    public function connect()
    {
        $ip = config('mt5.server');
        $port = config('mt5.port');
        $login = config('mt5.login');
        $password = config('mt5.password');
        $timeout = 3000;

        //--- create connection class
        $this->m_connect = new MTConnect($ip, $port, $timeout, $this->m_is_crypt);
        //--- create connection
        if (($error_code = $this->m_connect->Connect()) != MTRetCode::MT_RET_OK) return $error_code;
        //--- authorization to MetaTrader 5 server
        $auth = new MTAuthProtocol($this->m_connect, $this->m_agent);
        //---
        $crypt_rand = '';
        if (($error_code = $auth->Auth($login, $password, $this->m_is_crypt, $crypt_rand)) != MTRetCode::MT_RET_OK) {
            //--- disconnect
            $this->disconnect();
            return $error_code;
        }
        //--- if need crypt
        if ($this->m_is_crypt) $this->m_connect->SetCryptRand($crypt_rand, $password);
        //---
        return MTRetCode::MT_RET_OK;
    }

    /**
     * Check connection
     * @return bool
     */
    public function isConnected()
    {
        return $this->m_connect != null;
    }

    /**
     * Disconnect from MetaTrader 5 server
     * @return void
     */
    public function disconnect()
    {
        if ($this->m_connect) $this->m_connect->Disconnect();
    }

    /**
     * Create trade record such as Deposit or Withdrawal
     * @param Trade $trade
     * @return Trade
     * @throws ConnectionException
     * @throws TradeException
     */
    public function trade(Trade $trade): Trade
    {
        if (!$this->isConnected())
        {
            $conn = $this->connect();
            if ($conn != MTRetCode::MT_RET_OK)
            {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_trade = new MTTradeProtocol($this->m_connect);
        $ticket = null;

        $call =  $mt_trade->TradeBalance($trade->getLogin(), $trade->getType(), $trade->getAmount(), $trade->getComment(), $ticket);
        if ($call != MTRetCode::MT_RET_OK)
        {
            throw new TradeException(MTRetCode::GetError($call));
        }
        $trade->setTicket($ticket);
        return $trade;
    }

    /**
     * Create new User
     * @param User $user
     * @return User
     * @throws ConnectionException
     * @throws UserException
     */
    public function createUser(User $user): User
    {
        if (!$this->isConnected())
        {
            $conn = $this->connect();
            if ($conn != MTRetCode::MT_RET_OK)
            {
                throw new ConnectionException(MTRetCode::GetError($conn));
            }
        }
        $mt_user = new MTUserProtocol($this->m_connect);
        $mtUser = MTUser::CreateDefault();
        $mtUser->Group = $user->getGroup();
        $mtUser->Name = $user->getName();
        $mtUser->Email = $user->getEmail();
        $mtUser->Address = $user->getAddress();
        $mtUser->City = $user->getCity();
        $mtUser->State = $user->getState();
        $mtUser->Country = $user->getCountry();
        $mtUser->MainPassword = $user->getMainPassword();
        $mtUser->Phone = $user->getPhone();
        $mtUser->PhonePassword = $user->getPhonePassword();
        $mtUser->InvestPassword = $user->getInvestorPassword();
        $mtUser->Group = $user->getGroup();
        $mtUser->Leverage = $user->getLeverage();
        $mtUser->ZipCode = $user->getZipCode();

        $newMtUser = MTUser::CreateDefault();
        $result = $mt_user->Add($mtUser, $newMtUser);
        if ($result != MTRetCode::MT_RET_OK)
        {
            throw new UserException(MTRetCode::GetError($result));
        }
        $user->setLogin($newMtUser->Login);
        return $user;
    }
}
