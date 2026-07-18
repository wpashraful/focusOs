# 🎯 FocusOS User Guideline

Welcome to **FocusOS** — a premium, AI-powered productivity ecosystem designed to help you organize work, maintain focus, and execute goals with an intelligent anti-procrastination AI Coach.

This guide details the core modules of the application, how they interact, and how to set up and use the platform effectively.

---

## 📖 Table of Contents
1. [Core Philosophy & Architecture](#-core-philosophy--architecture)
2. [Quick Start & Setup](#%EF%B8%8F-quick-start--setup)
3. [Key Modules & How to Use Them](#-key-modules--how-to-use-them)
   - [Workspaces & Active Projects](#1-workspaces--active-projects)
   - [Goals, Tasks, & Routine Timeline](#2-goals-tasks--routine-timeline)
   - [Daily Targets & KPIs](#3-daily-targets--kpis)
   - [AI Coach & Chat Interfaces](#4-ai-coach--chat-interfaces)
   - [Project Knowledge Base (Resources)](#5-project-knowledge-base-resources)
   - [Future Ideas Board](#6-future-ideas-board)
   - [Progress & Review Dashboard](#7-progress--review-dashboard)
4. [🤖 Telegram Bot Integration](#-telegram-bot-integration)
   - [Linking Your Telegram Account](#linking-your-telegram-account)
   - [Bot Commands Reference](#bot-commands-reference)
5. [⚙️ Customizing the AI Model (Project Settings)](#%EF%B8%8F-customizing-the-ai-model-project-settings)

---

## 🧠 Core Philosophy & Architecture
FocusOS isn't a passive task manager. It is built as a **closed-loop system**:
* **High-Level Strategy**: Projects are broken down into **Phases**, **Goals**, and **Tasks**.
* **Daily Execution**: A dynamic **Routine Engine** compares what you should be doing *now* with your actual activity.
* **Intelligent Assistance**: An **AI Coach** intercepts off-topic discussions, tracks your habits, accesses uploaded documents (RAG), and prompts you via Web or **Telegram** to ensure you stay on track.

---

## 🛠️ Quick Start & Setup

### Prerequisites
* PHP 8.2+
* Composer
* Node.js & NPM
* MySQL / SQLite database

### Installation Steps

1. **Clone the repository:**
   ```bash
   git clone https://github.com/wpashraful/focusOs.git
   cd focusOs
   ```

2. **Install dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment:**
   Copy `.env.example` to `.env` and fill in your database credentials:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Run migrations and seeders:**
   ```bash
   php artisan migrate --seed
   ```

5. **Start the local servers:**
   Run the backend and queue processor:
   ```bash
   php artisan serve
   php artisan queue:work
   ```
   In a separate terminal, compile assets:
   ```bash
   npm run dev
   ```

---

## 🧩 Key Modules & How to Use Them

### 1. Workspaces & Active Projects
Everything in FocusOS resides in a **Workspace**. 
* Create or join a workspace.
* Create a **Project** and set it to **Active**.
* Define a **Phase Name** (e.g. `Alpha Launch`), a **Phase Goal**, and a **Phase End Date**. The AI coach reads this active phase context to guide your conversations.

### 2. Goals, Tasks, & Routine Timeline
* **Goals**: High-level key results (e.g. `Improve SEO`).
* **Tasks**: Actionable todo items linked to goals, complete with due dates, priority (`high`, `medium`, `low`), and estimated completion times.
* **Routine Engine**: Set up slots in your day (e.g. `09:00 - 11:00: Deep Work`). The dashboard highlights the current slot in real-time, prompting you to align your focus.

### 3. Daily Targets & KPIs
KPIs let you track recurring metric targets:
* Create a Target (e.g. `Send 15 outreach emails`).
* Increment counts directly. The AI Coach can also execute the `UpdateDailyLog` tool behind the scenes when you text it: *"I sent 5 more emails."*

### 4. AI Coach & Chat Interfaces
Accessible via the sidebar **AI Coach**:
* Talk to the coach regarding daily progress.
* **Procrastination Interceptor (FocusGuard)**: If you start talking to the coach about video games, movies, or other off-topic subjects, the coach politely redirects you back to your active project goal.
* **Tool Calling**: The AI automatically executes actions like creating tasks, completing tasks, updating daily metrics, and saving future ideas based on natural conversation.

### 5. Project Knowledge Base (Resources)
Under the **Resources** tab:
* Upload TXT, MD, or PDF documents relevant to your project.
* The system automatically extracts, chunks, and indexes the documents.
* When you ask the AI coach a question (e.g., *"What were the design rules in the PDF I uploaded?"*), FocusOS uses a **RAG keyword retriever** to inject matching excerpts directly into the prompt context.

### 6. Future Ideas Board
Under the **Future Ideas** tab:
* Quickly jot down sudden thoughts or brainstorms without losing focus.
* Group ideas into tabs: **Pending**, **Reviewed**, and **Promoted**.
* Click **Promote** to transition your idea into a concrete project task instantly.

### 7. Progress & Review Dashboard
Under the **Progress** tab:
* View your **Daily Focus Score** (percentage of daily tasks completed).
* Track performance trends over a **7-Day Bar Chart**.
* Review goal-by-goal progress and metric sparklines over the past week.

---

## 🤖 Telegram Bot Integration

FocusOS features complete Telegram integration. You can chat with your AI Coach and execute actions directly on the go.

### Linking Your Telegram Account
1. Log in to the web app, and navigate to **Settings → Telegram**.
2. Click **Generate Code** to receive a one-time verification token.
3. Open your Telegram bot and enter `/start <your-code>`. Your account is now securely linked!

### Bot Commands Reference
The bot understands standard chat text (delegating to the AI Coach) as well as the following rapid commands:

| Command | Action | Example |
| :--- | :--- | :--- |
| `/tasks` | List today's pending tasks. | `/tasks` |
| `/status` | Get a summary of daily progress & metric counts. | `/status` |
| `/done {title}` | Instantly mark a task as completed. | `/done Deploy API` |
| `/idea {text}` | Save a new idea to your Future Ideas Board. | `/idea Build mobile app` |

---

## ⚙️ Customizing the AI Model (Project Settings)
You can fine-tune the AI configuration for each project. Navigate to **Projects → View Project**:
1. Select your preferred **AI Provider** (e.g., Gemini, OpenAI, etc.).
2. Set the **Model Name** (e.g., `gemini-1.5-flash`).
3. Set **Temperature** and **Max Tokens**.
4. Define a **System Prompt Override** to instruct the Coach to behave in a specific style (e.g., *"Act like an aggressive startup manager"* or *"Be a gentle, mindful coach"*).
