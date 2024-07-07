create table mailing_list (
    `username` TEXT UNIQUE,
    code int(6) not NULL,
    issue_time datetime default current_timestamp,
    code_sent BOOLEAN DEFAULT false,
    trials INT DEFAULT 0
);