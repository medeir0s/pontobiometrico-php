<?php

/* 
-Adicionar calendário com datas onde não haverá expediente mas precisa compensar horas
*/

/*
    Gera a conexão, retorna conexão $conn
*/
function conectar() {
    $dsn = 'mysql:host=localhost;dbname=db';
    $username = 'root';
    $password = 'password';
    $options = array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
    );
    $conn = new PDO($dsn,$username,$password,$options);
    
    return $conn;
}

class Pessoa{
    public $nome,$id,$data,$chegada1,$saida1,$chegada2,$saida2,$cargahoraria,$horastrab;

    public function __construct($nome,$id,$data,$chegada1,$saida1,$chegada2,$saida2,$cargahoraria,$horastrab) {
        $this->nome = $nome;
        $this->id = $id;
        $this->data = $data;
        $this->chegada1 = $chegada1;
        $this->chegada2 = $chegada2;
        $this->saida1 = $saida1;
        $this->saida2 = $saida2;
        $this->cargahoraria = $cargahoraria;
        $this->horastrab = $horastrab;
      }
}
/*

    Funções independentes:


*/

/*
    Define o caminho a partir de onde deverão ser lidos os arquivos .txt
    Neste caso o servidor está na rede local, e foi montado em /mnt/ponto/.
*/

function definir_path(){
    $data =  date('d/m/Y');
    $datacortada = explode('/',$data);
    $dia = $datacortada[0];
    $ano = $datacortada[2];
    switch($datacortada[1]){
        
        case 1:
            $mes = 'jan.';
            break;
        case 2:
            $mes = 'fev.';
            break;
        case 3:
            $mes = 'mar.';
            break;
        case 4:
            $mes = 'abr.';
            break;
        case 5:
            $mes = 'mai.';
            break;
        case 6:
            $mes = 'jun.';
            break;
        case 7:
            $mes = 'jul.';
            break;
        case 8:
            $mes = 'ago.';
            break;
        case 9:
            $mes = 'set.';
            break;
        case 10:
            $mes = 'out.';
            break;
        case 11:
            $mes = 'nov.';
            break;
        case 12:
            $mes = 'dez.';
            break;
    }
    if(strlen($dia) == 1){
        $dia = '0'.$dia;
    }
    $filepath = '/mnt/ponto/punchlog_'.strval($dia).'.'.strval($mes).strval($ano).'.txt';
    return $filepath;
}


/*
    Cria uma cópia local do banco de dados do microsoft access, que contém a lista de usuários.    
*/
function copiar_mdb(){
    $home = $_SERVER['HOME'];
    $origem = '/mnt/ponto/GrFinger Desktop Identity.mdb';
    $destino = $home.'/users.mdb';
    if(!is_file($origem)){
        log_('Arquivo '.$origem.' não existe ou não é acessível');
    }
    if(!copy($origem,$destino)){
        log_("Falha ao copiar $origem...");
    }else{
        echo 'Arquivo copiado com sucesso';
    }
}

/*
    Loga os erros no current working dir -> getcwd()
*/
function log_($string){ 
    //$log = fopen($_SERVER['HOME'].get_current_user().'log_'.date("d.m.Y").'.txt', "w"); //<- joga em home/dpu
    $log = fopen('log_'.date("d.m.Y").'.txt', "w");
    fwrite($log,date('H:i').' '.$string);
    fclose($log);

}


/*
    Recebe um horário no formato hh:mm:ss e retorna em segundos
*/
function horario_to_seg($horario){
    sscanf($horario, "%d:%d:%d", $hours, $minutes, $seconds);
    $time_seconds = isset($seconds) ? $hours * 3600 + $minutes * 60 + $seconds : $hours * 60 + $minutes;
    //print(" Hora : $hours, Minutos: $minutes, Segundos: $seconds, Total: $time_seconds");
    return $time_seconds;
}

/*
    Define as horas trabalhadas baseado nos horários em que o funcionário bateu o ponto.
*/
function definir_horas_trabalhadas($chegada1,$chegada2,$saida1,$saida2){
    echo "$chegada1    $chegada2    $saida1    $saida2    ";
    $zero = '00:00:00';
    if($saida1 == NULL){
        $saida1 = $chegada1;
        $saida2 = $zero;
        $chegada2 = $zero;
    }elseif($chegada2 == NULL && $saida2 == NULL){
        $chegada2 = $zero;
        $saida2 = $zero;
    }elseif($saida2 == NULL){
        $saida2 = $chegada2;
    }

    echo "\n $chegada1    $chegada2    $saida1    $saida2    ";

    $chegou1 = horario_to_seg($chegada1);
    $chegou2 = horario_to_seg($chegada2);
    $saiu1 = horario_to_seg($saida1);
    $saiu2 = horario_to_seg($saida2);
    $metade1 = $saiu1-$chegou1;
    $metade2 = $saiu2-$chegou2;
    $horastrabalhadas = ($metade1+$metade2)/3600;

    echo "\nchegou1: $chegou1, saiu1: $saiu1, chegou2: $chegou2, 
    saiu2 : $saiu2, m1 = $metade1, m2=$metade2, 
    total = $horastrabalhadas ";

    return $horastrabalhadas;
}

/*
    Define a carga horária baseado no campo AppID, que é gerado pelo Griaule Biometrics quando você cadastra um novo usuário, e ele pede
    que digite um identificador.

*/
function definir_carga_horaria($id){
    if($id > 0 && $id < 940){
        $cargahoraria = 7;
    }elseif($id > 945 && $id < 995){
        $cargahoraria = 5;
    }elseif($id > 997 && $id < 3995){
        $cargahoraria = 4;
    }elseif($id > 4997 && $id < 6000){
        $cargahoraria = 8;
    }
}
/*
    Lista todos os .txt com 'punchlog_' do $dir, retorna um array $files
*/
function listar_txt($dir){ 
    chdir($dir);
    $files = glob('punchlog_*.txt');
    foreach($files as $file){
        echo "$file\n";
    }
    
    return $dir;
}


/*
    Recebe o objeto pessoa, organiza as informações e envia o SQL para 
    o banco de dados. Retorna True ou False.
*/
function submeterdados(Pessoa $pessoa){
    $zero = '00:00:00';
    if($pessoa->saida1 == NULL){
        $pessoa->saida1 = $pessoa->chegada1;
        $pessoa->saida2 = $zero;
        $pessoa->chegada2 = $zero;
    }elseif($pessoa->chegada2 == NULL && $pessoa->saida2 == NULL){
        $pessoa->chegada2 = $zero;
        $pessoa->saida2 = $zero;
    }elseif($pessoa->saida2 == NULL){
        $pessoa->saida2 = $pessoa->chegada2;
    }
    /*
    echo "$pessoa->chegada1    $pessoa->chegada2    $pessoa->saida1    
    $pessoa->saida2    ";
    
    $conn = conectar(); //recebe conexão
    $query = sprintf("");
    $result = $conn->query($query);
    $result->debugDumpParams();
    */
    sscanf($pessoa->data, "%d/%d/%d", $dia, $mes, $ano);
    if(strlen($dia)==1){
        $dia = '0'.$dia;
    }
    if(strlen($mes)==1){
        $mes = '0'.$mes;
    }
    //Formata para o tipo DATETIME do MySQL - 'YYYY-MM-DD HH:MM:SS'
    $final = $ano.'-'.$mes.'-'.$dia.' ';
    $chegadafinal1 = $final.$pessoa->chegada1;
    $chegadafinal2 = $final.$pessoa->chegada2;
    $saidafinal1 = $final.$pessoa->saida1;
    $saidafinal2 = $final.$pessoa->saida2;
    /*
            Continuar apos linha 45 depois de passar o script
    */


    

}



/*
    Lê os usuários do arquivo MDB e envia ao MySql
*/
function atualizar(){
    $dbName = $_SERVER['HOME'] . "/users.mdb";
    
     if (!file_exists($dbName)) 
        log_("Arquivo $dbName não existe!!");
    
    $connection = odbc_connect("YourDSN","",""); 
    /*
        Tive que editar o arquivo /etc/odbc.ini

        [YourDSN]

        Description = This is the configured DSN for your access db

        Driver = MDBTools

        ServerName = localhost

        Database = /home/dpu/users.mdb
    */
    $query = 'SELECT Name,ID,AppID FROM subjects';
    $result = odbc_exec($connection,$query);


    //conexão ao mysql
    $conn =  conectar();
    $inserir = $conn->prepare("INSERT IGNORE INTO usuario 
    SET idusuario = :id, nome = :nome ,grupo = :grupo;");
    $inserir->bindParam(':id',$ID);
    $inserir->bindParam(':nome',$nome);
    $inserir->bindParam(':grupo',$grupo);
    
    while($row = odbc_fetch_array($result))
    {   
        //Setando as variáveis de cada usuário
        $nome = $row["Name"];
        $ID = $row["ID"];
        if($row["AppID"] > 0 && $row["AppID"] < 940)
        {
            $grupo = 4; //Servidor, CH = 7
        }
        elseif($row["AppID"] > 3997 && $row["AppID"] < 4997)
        {
            $grupo = 2; //Terceirizado, CH=7
        }
        elseif($row["AppID"] > 945 && $row["AppID"] < 995)
        {
            $grupo = 6; //Jornalista, CH=5
        }
        elseif($row["AppID"] > 997 && $row["AppID"] < 3995)
        {
            $grupo = 1; //Estagiário, CH=4
        }
        elseif($row["AppID"] > 4997 && $row["AppID"] < 6000)
        {
            $grupo = 5; //Motorista, CH=8
        }
        //Enviando ao MySQL via prepared statement
        $inserir->execute();
        //Duas linhas abaixo são para debug:
        //echo mb_detect_encoding($nome);
        //var_dump($nome);
    }
    //fechando conexões
    $conn = null;
    $inserir = null;
}



/* 

    Script principal
    É uma função, para facilitar construir o banco de registros. 
    Recebe o path do arquivo onde atuará e retorna true ou false.


*/
function script($filepath){
    $dir = $filepath;
    if(!is_file($dir)){    #verifica se o arquivo existe ou não
        log_("arquivo $dir não existe");
    }

    $handle = fopen($filepath, "r");
    $array = array();
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            echo utf8_encode($line); #Transforma do ISO-8859-1 do Windows para UTF-8 do GNU/Linux
            $array = utf8_encode($line);
        }
        fclose($handle);
    }
} //#####################################incompleto########################################################


//Padrão de chamada:
//script(definir_path());

//Para testes:
//script('/mnt/ponto/punchlog_01.fev.2016.txt');




//listar_txt('/mnt/ponto');


//definir_horas_trabalhadas('07:43:20','13:00:00','12:01:02','18:03:50');


//horario_to_seg(date('H:i:s'));

//$usuario = new Pessoa('Fulano',5,'6/11/2017','07:43:20','13:00:00','12:01:02','18:03:50','carga',7.66);
//submeterdados($usuario);




?>
