-- Table des arretes ministeriels par filiere (nouveau modele de diplome 2025+)
-- Genere le 2026-08-06 depuis "Liste des gradues par mention 2026"

CREATE TABLE IF NOT EXISTS diplome_arretes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  programme VARCHAR(150) NOT NULL,
  accronyme VARCHAR(10) DEFAULT NULL,
  arrete_mesupres VARCHAR(255) DEFAULT NULL,
  arrete_cneat VARCHAR(255) DEFAULT NULL,
  last_change_user_id VARCHAR(50) DEFAULT NULL,
  last_change_datetime DATETIME DEFAULT NULL,
  UNIQUE KEY uk_programme (programme)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Licence Gestion','GE','3992/2022-MESupRES du 28 février 2022','26826/2012 – CNEAT du 13 septembre 2012',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Licence Sciences Infirmières','SI','12.258/2022 - MESupRES du 16 mai 2022','31577/2012 – CNEAT du 08 novembre 2012',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Licence Théologie','TH','10.275/2021-MESupRES du 24 juin 2021','26594 /2021 – CNEAT du 01 juillet 2021',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Licence Informatique','IN','10.275/2021-MESupRES du 07 avril 2021','26592 /2021 – CNEAT du 01 juillet 2021',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Licence Communication','CO','3992/2022-MESupRES du 28 février 2022','26593 /2021 – CNEAT du 22 octobre 2021',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Licence Études anglophones','EA','3992/2022-MESupRES du 28 février 2022','15816/2022 – CNEAT du 07 avril 2022',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Licence en Droit','DRT','3992/2022-MESupRES du 28 février 2022','15815/2022 - CNEAT du 07 avril 2022',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Master en Gestion','GE','3992/2022-MESupRES du 28 février 2022','15818/2022 - CNEAT du 07 Avril 2022',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Master en Informatique','IN','10.275/2021-MESupRES du 07 avril 2021','26595 /2021 – CNEAT du 22 octobre 2021',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
INSERT INTO diplome_arretes(programme,accronyme,arrete_mesupres,arrete_cneat,last_change_datetime) VALUES('Master en Etudes Anglophones','EA','3992/2022-MESupRES du 28 février 2022','15817/2022 - CNEAT du 07 avril 2022',NOW())
  ON DUPLICATE KEY UPDATE accronyme=VALUES(accronyme),arrete_mesupres=VALUES(arrete_mesupres),arrete_cneat=VALUES(arrete_cneat);
