-- MySQL dump 10.13  Distrib 5.1.63, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: jklfood
-- ------------------------------------------------------
-- Server version	5.1.63-0ubuntu0.10.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `sources`
--

DROP TABLE IF EXISTS `sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sources` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `selector` text NOT NULL,
  `subselector` text NOT NULL,
  `sameline` tinyint(1) NOT NULL DEFAULT '0',
  `brbreak` tinyint(1) NOT NULL DEFAULT '0',
  `url` text NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `name` varchar(64) NOT NULL DEFAULT '',
  `skip` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=33 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sources`
--

LOCK TABLES `sources` WRITE;
/*!40000 ALTER TABLE `sources` DISABLE KEYS */;
INSERT INTO `sources` VALUES (24,'html body div.wrapper div.bg_layout table tbody tr td div.sisalto table tbody tr td table.contentpaneopen tbody tr td table tbody tr td','p.MsoNormal',0,0,'http://www.oldbricksinn.fi/index.php?option=com_content&task=view&id=18&Itemid=28',1,'OB Inn'),(31,'html body form#aspnetForm div#container div#content div#middle div#middleinner div.xmldoc','p',0,0,'http://www.jamk.fi/yleisolle/ravintoladynamo/lounaslista',1,'Dynamo'),(23,'html body div#wrapper div#content-wrapper-2col div#content-col-left div#main-content','h2, p',0,0,'http://www.torero.fi/jyvaskyla/lounas/',1,'Torero'),(21,'html body div#wrapper div#left table','td',0,0,'http://www.ravintolashalimar.fi/index.php?page=lunch',1,'Shalimar'),(22,'#rt-feature .rt-container .module-content .in-module-content','.custom',0,0,'http://www.ravintolaidea.fi/index.php/menut/lounas',1,'Idea Lutakko'),(20,'html body div#haraldBgImage div#page div#paperWrap div#paper div#paperContentRepeat div#paperContentTop div#content div.float table#lounaslistaTable','td',0,0,'http://www.ravintolaharald.fi/ruoka--ja-juomalistat/lounas',1,'Harald'),(25,'html body div#all div#contentarea div#wrapper div#main div#page table','td',0,0,'http://www.elonen.fi/yritys/cafe-elonen-innova-ruokalista.html',1,'Elonen Innova'),(26,'html body div#all div#contentarea div#wrapper div#main div#page table','td',0,0,'http://www.elonen.fi/yritys/cafe-elonen-jyvaskeskus-ruokalista.html',1,'Elonen Jyväskeskus'),(27,'html body div#main div#main0 div div.div1of2','p',1,0,'http://www.bobichef.fi/',0,'Bobi Chef'),(28,'html body.text','p.listatext',1,0,'http://www.lounasinfo.fi/menuframe.php?&c=Suomi&t=Jyv%E4skyl%E4&a=Keskusta&r=40',1,'Figaro WB'),(29,'html body div.wrapper div.content div#weekListContainer','div h2, div span',0,0,'http://www.lounaskeskimaa.fi/lounas_ravintola?rid=188&date=',1,'CH Jyväshovi'),(32,'html body div.wrapper div.content div#weekListContainer','div h2, div span',0,0,'http://www.lounaskeskimaa.fi/lounas_ravintola?rid=203&date=',1,'Trattoria Aukio');
/*!40000 ALTER TABLE `sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `synonyms`
--

DROP TABLE IF EXISTS `synonyms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `synonyms` (
  `parent` tinytext NOT NULL,
  `word` tinytext NOT NULL,
  UNIQUE KEY `word` (`word`(16))
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `synonyms`
--

LOCK TABLES `synonyms` WRITE;
/*!40000 ALTER TABLE `synonyms` DISABLE KEYS */;
INSERT INTO `synonyms` VALUES ('kana','kana'),('kana','broiler'),('kana','kalkkuna'),('kana','ankka'),('liha','liha'),('liha','pytti'),('liha','makkara'),('liha','porsa'),('liha','possu'),('liha','nauta'),('liha','naudan'),('liha','riista'),('liha','chorizo'),('liha','kebab'),('liha','lammas'),('liha','poro'),('liha','härkä'),('kasvis','kasvis'),('kasvis','soija'),('keitto','keitto'),('keitto','rokka'),('keitto','soppa'),('kala','kala'),('kala','lohi'),('kala','lohta'),('kala','lohen'),('kala','ahven'),('kala','rapu'),('kala','ravun'),('kala','kampela'),('kala',' sei'),('kala','turska'),('kala','siika');
/*!40000 ALTER TABLE `synonyms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'jklfood'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2013-01-03 21:12:17
