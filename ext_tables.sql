#
# Table structure for table 'tx_watchword_domain_model_watchword'
#
CREATE TABLE tx_watchword_domain_model_watchword (
    cruser_id int(11) unsigned NOT NULL DEFAULT '0',
    date int(11) unsigned NOT NULL DEFAULT '0',
    year int(4) unsigned NOT NULL DEFAULT '0',
    weekday varchar(30) NOT NULL DEFAULT '',
    sunday_name varchar(255) NOT NULL DEFAULT '',
    watchword_verse varchar(255) NOT NULL DEFAULT '',
    watchword_text text,
    teaching_verse varchar(255) NOT NULL DEFAULT '',
    teaching_text text,

    UNIQUE KEY date (date)
);
