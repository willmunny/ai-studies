<?php
class Vector2{
   public $X;
   public $Y;

   public function __construct($x, $y){
      $this->X = $x;
      $this->Y = $y;
   }

   public static final function Zero(){
      return new Vector2( 0, 0);
   }

   public static final function Distancia( Vector2 $a, Vector2 $b ) {
      $catetoX = $b->X - $a->X;
      $catetoY = $b->Y - $a->Y;

      $hipotenusa = sqrt( pow($catetoX, 2) + pow($catetoY, 2) );
      return intval( $hipotenusa );
   }
}

class Acao {
   const ESQUERDA = 'E';
   const DIREITA  = 'D';
   const CIMA     = 'C';
   const BAIXO    = 'B';

   public $Nome;
   public $Origem;
   public $Destino;
   public $Probabilidade;

   public function __construct($nome,Estado $origem,Estado $destino,$probabilidade=1){
      $this->Nome = $nome;
      $this->Origem = $origem;
      $this->Destino = $destino;
      $this->Probabilidade = $probabilidade;
   }
}

class Estado {
   public $Posicao;
   public $Sensores;
   public $Acoes = array();

   public function __construct(Vector2 $p, $sensores){
      $this->Posicao = $p;
      $this->Sensores  = $sensores;
   }

   public function adicionarAcao(Acao $a) {
      $this->Acoes[] = $a;
   }

   public function __toString(){
      return "{$this->Posicao->X},{$this->Posicao->Y} Sensor({$this->Sensores})";
   }
}

class Palpite {
   public $Estado;
   public $Probabilidade;

   public function __construct($p, $e){
      $this->Probabilidade = $p;
      $this->Estado = $e;
   }

   static function Comparar(Palpite $a, Palpite $b) {
      $result = false;
      if ($a->Probabilidade != $b->Probabilidade) {
         $result = $a->Probabilidade < $b->Probabilidade ? true : false;
      }
      return $result;
   }

   public function __toString(){
      return "P({$this->Estado->Posicao->X},{$this->Estado->Posicao->Y}) = {$this->Probabilidade}";
   }
}

class No {
   public $Pai;
   public $Custo;
   public $Estado;
   public $Politica;

   public function __construct ( $pai, $estado, $custo, $politica ) {
      $this->Custo = $custo;
      $this->Estado = $estado;
      $this->Politica = $politica;
      $this->Pai = $pai;
   }

   static function compararCusto(No $a, No $b) {
      $result = false;
      if ($a->Custo != $b->Custo) {
         $result = $a->Custo > $b->Custo ? true : false;
      }
      return $result;
   }
}

class Errante {
   public $Mapa = array();
   public $Gol;
   public $Palpites = array();
   private $Custo   = 1;
   private $Recompensa = 100;
   private $Politicas = array();

   public function __construct($mapa, Estado $gol, $sensores) {
      $this->Gol = $gol;
      $this->Mapa = $mapa;
      $compativeis = array();
      foreach ($mapa as $e) {
         if ( $e->Sensores==$sensores ) {
           $compativeis[] = $e;
         }
      }
      $N = count($compativeis);
      foreach ($compativeis as $estado) {
         $this->Palpites[] = new Palpite((1/$N),$estado);
      }

      $this->MontarPoliticas();
   }

   public function MontarPoliticas () {
      foreach ($this->Mapa as $index=>$estado_inicial) {
         $melhor = null;
         $fronteira = array();
         $inesplorados = array();

         $fronteira[] = new No(null,$estado_inicial, 0, null);
         for ($i=0; $i<count($this->Mapa); $i++) {
            if ($i!=$index) {
               $inesplorados[] = new No(null,$this->Mapa[$i], $this->Custo, null);
            }
         }

         $encontrado = false;
         while (!$encontrado) {
            usort($fronteira, array ("No", "compararCusto"));
            $noSelecionado = $fronteira[0];
            if (($melhor && $noSelecionado->Custo>=$melhor->Custo) || count($fronteira)==0 ) {
               $encontrado = true;
            } else {
               if ($noSelecionado->Estado->Posicao == $this->Gol->Posicao) {
                  $melhor = $noSelecionado;
                  array_shift($fronteira);
               } else {
                  $vizinhos = array();
                  foreach ($noSelecionado->Estado->Acoes as $acao) {
                     foreach ($inesplorados as $k=>$inesplorado) {
                        if ( $inesplorado->Estado->Posicao == $acao->Destino->Posicao ) {
                           $inesplorado->Custo += $noSelecionado->Custo;
                           $inesplorado->Pai = $noSelecionado;
                           $inesplorado->Politica = $acao->Nome;
                           $vizinhos[] = $inesplorado;
                           unset($inesplorados[$k]);
                           break;
                        }
                     }
                  }
//                  $this->PrintPoliticas( $noSelecionado );
//                  print "\n";
                  array_shift($fronteira);
                  if (count($vizinhos)>0) {
                     $fronteira = array_merge($fronteira,$vizinhos);
                  }
               }
            }
         }
         $politicaInicial = null;
         $this->EncontrarPoliticaInicial( $politicaInicial, $melhor);
         $this->Politicas[$index] = $politicaInicial;
      }
   }

   private function EncontrarPoliticaInicial( &$politicaInicial, No $no) {
      if ($no->Pai) {
         $politicaInicial = $no->Politica;
         $this->EncontrarPoliticaInicial( $politicaInicial, $no->Pai);
      }
   }

   private function PrintPoliticas( No $no) {
      if ($no->Pai) {
         $politicaInicial = $no->Politica;
         $this->PrintPoliticas( $no->Pai);
         print $no->Politica;
      }
   }

   public function EscolherAcao () {
      $politicas = array();
      $acao = null;
      foreach ($this->Palpites as $p) {
         foreach ($this->Mapa as $id=>$e) {
            if ( $p->Estado->Posicao == $e->Posicao ) {
               $politicas[$this->Politicas[$id]]++;
            }
         }
      }
      arsort($politicas);
      foreach ($politicas as $nomeAcao=>$__sem_uso) {
         $acao = $nomeAcao;
         break;
      }
      return $acao;
   }

   public function Movase($ordem,$sensores) {
      $compativeis = array();
      foreach ($this->Palpites as $p) {
         foreach ($p->Estado->Acoes as $a) {
            if ($a->Nome==$ordem && $a->Destino->Sensores==$sensores) {
               $compativeis[] = new Palpite( $a->Probabilidade,$a->Destino);
            }
         }
      }
      $this->Palpites = array();
      $N = count($compativeis);
      foreach ($compativeis as $palpite) {
         $probabilidade = $palpite->Probabilidade * (1/$N);
         $palpite->Probabilidade = $probabilidade / ($probabilidade + (1-$probabilidade));
         $duplicado = false;
         foreach ($this->Palpites as $k=>$p_verificar) {
            if ($p_verificar->Estado==$palpite->Estado) {
               $duplicado = true;
               $this->Palpites[$k]->Probabilidade+=$palpite->Probabilidade;
            }
         }
         !$duplicado && $this->Palpites[] = $palpite;
      }

   }

   public function ImprimirPalpites() {
      foreach ($this->Palpites as $p) {
         print "{$p}\n";
      }
   }
}


/*             ----CONFIGURANDO O MUNDO----
$desenho = <<<EOT
#########
#@Z@@@@@#
#@###@#X#
#@@@@@@@#
#########
EOT;
# - bloco
@ - passagem
X - alvo
Z - Inicial
*/
$mapa_selecionado = array(
0 => array("#","#","#","#","#","#","#","#","#"),
1 => array("#","@","Z","@","@","@","@","@","#"),
2 => array("#","@","#","#","#","@","#","X","#"),
3 => array("#","@","@","@","@","@","@","@","#"),
4 => array("#","#","#","#","#","#","#","#","#"),
   );

$estados = array();
$ligacoes = array();
foreach ( $mapa_selecionado as $n_linha=>$linha ) {
   foreach ($linha as $coluna => $valor) {
      if ( in_array($valor, array("@","Z","X"))===true ) { //passagens
         $sensor = '';
         if ( !isset($ligacoes["{$coluna},{$n_linha}"])) {
            $ligacoes["{$coluna},{$n_linha}"] = array();
         }
         if ( isset($mapa_selecionado[($n_linha-1)][$coluna]) && in_array($mapa_selecionado[($n_linha-1)][$coluna], array("@","Z","X"))===true ) { //sensor cima
            $sensor .= '0';
            $ligacoes["{$coluna},{$n_linha}"][$coluna.",".($n_linha-1)] = Acao::CIMA;
         } else {
            $sensor .= '1';
         }
         if ( isset($mapa_selecionado[$n_linha][($coluna+1)]) && in_array($mapa_selecionado[$n_linha][($coluna+1)], array("@","Z","X"))===true ) { //sensor direita
            $sensor .= '0';
            $ligacoes["{$coluna},{$n_linha}"][($coluna+1).",".$n_linha] = Acao::DIREITA;
         } else {
            $sensor .= '1';
         }
         if ( isset($mapa_selecionado[($n_linha+1)][$coluna]) && in_array($mapa_selecionado[($n_linha+1)][$coluna], array("@","Z","X"))===true ) { //sensor baixo
            $sensor .= '0';
            $ligacoes["{$coluna},{$n_linha}"][$coluna.",".($n_linha+1)] = Acao::BAIXO;
         } else {
            $sensor .= '1';
         }
         if ( isset($mapa_selecionado[$n_linha][($coluna-1)]) && in_array($mapa_selecionado[$n_linha][($coluna-1)], array("@","Z","X"))===true ) { //sensor esquerda
            $sensor .= '0';
            $ligacoes["{$coluna},{$n_linha}"][($coluna-1).",".$n_linha] = Acao::ESQUERDA;
         } else {
            $sensor .= '1';
         }
         $estados["{$coluna},{$n_linha}"] = new Estado(new Vector2($coluna,$n_linha), $sensor);
         if ( $valor == "X" ) {
            $destino = $estados["{$coluna},{$n_linha}"];
         } elseif ( $valor == "Z" ) {
            $inicial = $estados["{$coluna},{$n_linha}"];
         }
      }
   }
}

foreach ($ligacoes as $chave => $acoes) {
   foreach ($acoes as $chave_destino => $tipo_acao) {
      $estados[$chave]->adicionarAcao(new Acao($tipo_acao,$estados[$chave],$estados[$chave_destino],0.3));
   }
}
$estado_atual = $inicial;

$mundo = array();
foreach ($estados as $estado) {
   $mundo[] = $estado;
}


$robo = new Errante($mundo,$destino,$estado_atual->Sensores);

$im = imagecreatetruecolor(800,600);
$branco = imagecolorallocate($im, 255, 255, 255);
$preto = imagecolorallocate($im, 0, 0, 0);
$cinza = imagecolorallocate($im, 180, 180, 180);
$vermelho = imagecolorallocate($im, 250, 0, 0);
$verde = imagecolorallocate($im, 0, 250, 0);
imagefill($im, 0, 0, $branco);

$x0 = 10;
$y0 = 10;
$unit = 20;
desenhar($im, $branco, $preto, $cinza, $vermelho, $verde, $x0, $y0, $unit, $robo->Palpites, $mundo, $estado_atual, $destino);

while ($acao = $robo->EscolherAcao()) {
   $estado_atual = Natureza($mundo, $estado_atual, $acao );
   $robo->Movase($acao, $estado_atual->Sensores);

   $y0+=100;
   desenhar($im, $branco, $preto, $cinza, $vermelho, $verde, $x0, $y0, $unit, $robo->Palpites, $mundo, $estado_atual, $destino);
}

/*       ----DESENHOS---      */

header('Content-Type: image/png');
imagepng($im);
imagedestroy($im);

function desenhar(&$im, $branco, $preto, $cinza, $vermelho, $verde, $x0, $y0, $unit, $palpites, $mundo, $estado_inicial, $gol) {
   $fronteiraX = 0;
   $fronteiraY = 0;
   foreach ($mundo as $estado) {
      $fronteiraX = max($fronteiraX,$estado->Posicao->X);
      $fronteiraY = max($fronteiraY,$estado->Posicao->Y);
   }
   imagefilledrectangle( $im, $x0, $y0, ($x0+($fronteiraX*($unit))), ($y0+($fronteiraY*($unit))), $preto);

   foreach ($mundo as $estado) {
      $posX = ($x0+(($estado->Posicao->X-1)*$unit));
      $posY = ($y0+(($estado->Posicao->Y-1)*$unit));
      if ( $estado->Posicao != $gol->Posicao ) {
         imagefilledrectangle( $im, $posX, $posY, ($posX+$unit), ($posY+$unit), $branco);
      } else {
         imagefilledrectangle( $im, $posX, $posY, ($posX+$unit), ($posY+$unit), $verde);
      }
      imagerectangle( $im, $posX, $posY, ($posX+$unit), ($posY+$unit), $preto);
   }

   foreach ($palpites as $palpite) {
      $estado = $palpite->Estado;
      $posX = ($x0+(($estado->Posicao->X-1)*$unit));
      $posY = ($y0+(($estado->Posicao->Y-1)*$unit));
      imagefilledrectangle( $im, $posX, $posY, ($posX+$unit), ($posY+$unit), $cinza);
      imagerectangle( $im, $posX, $posY, ($posX+$unit), ($posY+$unit), $preto);
   }

   $posX = ($x0+(($estado_inicial->Posicao->X-1)*$unit)) + ($unit/2);
   $posY = ($y0+(($estado_inicial->Posicao->Y-1)*$unit)) + ($unit/2);
   imagefilledellipse($im, $posX, $posY, ($unit/2), ($unit/2), $vermelho );
}

function Natureza($mundo, $estado_inicial, $acao ) {
   foreach ($mundo as $estado) {
      if ( $estado->Posicao == $estado_inicial->Posicao ) {
         foreach ($estado->Acoes as $a) {
            if ( $a->Nome==$acao ) {
               return $a->Destino;
            }
         }
      }
   }
}