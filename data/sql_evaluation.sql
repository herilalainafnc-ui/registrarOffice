-- Table des formulaires d'évaluation
CREATE TABLE IF NOT EXISTS rg_eval_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    is_published TINYINT(1) DEFAULT 0,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    target_role VARCHAR(50) DEFAULT 'all', -- admin, teacher, student, all
    limit_one_response TINYINT(1) DEFAULT 1,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des questions
CREATE TABLE IF NOT EXISTS rg_eval_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type VARCHAR(50) NOT NULL, -- short, paragraph, multiple_choice, checkbox, dropdown, rating, stars, yes_no, date
    is_required TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    -- Champs spécifiques pour éval enseignant (facultatifs)
    target_prof_id INT NULL,
    target_course_id INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES rg_eval_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des options (pour choice, checkbox, dropdown)
CREATE TABLE IF NOT EXISTS rg_eval_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_text TEXT NOT NULL,
    display_order INT DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES rg_eval_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des participations / réponses (une entrée par utilisateur et par formulaire)
CREATE TABLE IF NOT EXISTS rg_eval_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    user_id INT NOT NULL, -- qui a répondu
    respondent_email VARCHAR(255) NULL, -- e-mail invité si la réponse vient d'un utilisateur non connecté
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES rg_eval_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des réponses individuelles aux questions
CREATE TABLE IF NOT EXISTS rg_eval_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    response_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT, -- Pour champs texte, date
    answer_number INT, -- Pour rating, stars, yes_no (1/0)
    FOREIGN KEY (response_id) REFERENCES rg_eval_responses(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES rg_eval_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison pour les options à sélection multiple (checkbox)
CREATE TABLE IF NOT EXISTS rg_eval_answer_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    answer_id INT NOT NULL,
    option_id INT NOT NULL,
    FOREIGN KEY (answer_id) REFERENCES rg_eval_answers(id) ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES rg_eval_options(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
