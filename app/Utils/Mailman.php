<?php 

namespace App\Utils;

use splattner\mailmanapi\MailmanAPI;
use Uspdev\Replicado\DB;
use App\Rules\MultipleEmailRule;
use App\Models\Lista;
use App\Models\Unsubscribe;
use Illuminate\Support\Facades\Http;

class Mailman
{
    public static function emails(Lista $lista)
    {
        //capturando emails do endpoint
        if(!empty($lista->url_externa) && !empty($lista->token)){
            $response = Http::get($lista->url_externa);
            dd($response->body());

            $newresponse = $basicauth->request(
            'GET',
            'api/1/curriculum',
            ['debug'   => true], 
            ['headers' => 
                [
                    'Authorization' => "Basic {$credentials},Bearer {$acceso->access_token}"
                ]
            ]
            )->getBody()->getContents();
        }


        $url = $lista->url_mailman . '/' . $lista->name;
        $mailman = new MailmanAPI($url,$lista->pass,false);

        /* Emails do replicado */
        $emails_replicado = []; 
        foreach($lista->consultas as $consulta){
            $emails_replicado[] = self::emails_replicado($consulta->replicado_query);
        }
        if(!empty($emails_replicado)){
            $emails_replicado = call_user_func_array('array_merge', $emails_replicado);
        }

        /* Emails adicionais */
        if(empty($lista->emails_adicionais)) {
            $emails_adicionais = [];
        }
        else {
            $emails_adicionais = explode(',',$lista->emails_adicionais);
        }
        $emails_updated = array_merge($emails_replicado,$emails_adicionais);

        /* Emails unsubscribed não podem fazer parte das listas */
        $emails_unsubscribed = Unsubscribe::where('id_lista', $lista->id)->get(['email'])->toArray();
        $emails_unsubscribed = array_column($emails_unsubscribed, 'email');
        $emails_updated = array_diff($emails_updated,$emails_unsubscribed);
       

        /* Emails allowed não podem fazer parte das listas */
        $emails_allowed = explode(',',$lista->emails_allowed);
        $emails_updated = array_diff($emails_updated,$emails_allowed);

        /* Enviando para mailman */
        $emails_updated = array_map( 'trim', $emails_updated );
        $emails_updated = array_unique($emails_updated);

        if(!empty($emails_updated)) {
            $emails_added = $mailman->syncMembers($emails_updated);
        } else {
            $remove = $mailman->getMemberlist();
            $mailman->removeMembers($remove);
        }
        /* Salvamos um cópia dos emails da última sincronização na lista */
        $lista->emails = implode(',',$emails_updated);
        $lista->save();

         /* Emails da lista */
        $emails_mailman = $mailman->getMemberlist();

        /* update stats */
        $lista->stat_mailman_updated = count($emails_updated);
        $lista->stat_mailman_after = count($emails_mailman);
        $lista->stat_mailman_date = date('Y-m-d H:i:s');
        $lista->save();
    }

    public static function emails_replicado($query){
        $result = \Uspdev\Replicado\DB::fetchAll($query);
        if($result) return array_column($result, 'codema');
        return [];
    }

    public static function config(Lista $lista) {
        $url = $lista->url_mailman . '/' . $lista->name;
        $mailman = new MailmanAPI($url,$lista->pass,false);

        $mailman->configGeneral($lista->name,
            config('listas.mailman_owner'),
            $lista->name,
            str_replace('@','',config('listas.mailman_suffix'))
        );

        $mailman->configPrivacySender(explode(',',$lista->emails_allowed));
        $mailman->configNonDigest(config('listas.mailman_footer'));
        $mailman->configPrivacySubscribing();
        $mailman->configPrivacyRecipient();
        $mailman->configDigest();
        $mailman->configBounce();
        $mailman->configArchive();
    }

    public static function getArchive(Lista $lista) {
        $url = $lista->url_mailman . '/' . $lista->name;
        $mailman = new MailmanAPI($url,$lista->pass,false);
        return  $mailman->getArchive($lista->name, date("Y"));
    }
}