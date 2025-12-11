# Smart Expense Tracking App

A full-stack, modern, and intuitive web application designed to help you manage your personal finances with ease. Track your expenses, create budgets, and gain insights into your spending habits through a beautiful and responsive interface.

This application is built with the **TALL stack** (Tailwind CSS, Alpine.js, Laravel, and Livewire), offering a reactive, single-page application experience with the power and simplicity of PHP.

---

## ✨ Features

-   **Interactive Dashboard:** Get a comprehensive overview of your finances for the current month, including total spending, budget adherence, and a 6-month spending trend chart.

-   **Expense Tracking:** Easily add, view, and manage your daily expenses.

-   **Budget Management:** Set monthly budgets for your entire spending or for specific categories to keep your finances in check. Visualize your progress with clear indicators.

-   **Category Organization:** Create custom categories with unique colors to classify your expenses and understand where your money is going.

-   **Recurring Expenses:** Track subscriptions and recurring payments automatically.

-   **Data Visualization:** Interactive charts for spending by category and monthly trends, powered by Chart.js.

-   **Responsive Design:** A seamless experience across desktop, tablet, and mobile devices.

-   **Dark Mode:** Easy on the eyes for late-night budgeting sessions.

-   **Real-time Feedback:** Instant form validation and updates powered by Livewire.

## 📸 Screenshots

### Dashboard

![Dashboard](screenshots/dashboard.png)

### Budgets

![Budgets](screenshots/budgets.jpg)

### Budgets AI recommendations

![Budgets](screenshots/Budget_AiRecommendations.jpg)

### Categories

![Categories](screenshots/categories.png)

### Expenses

![Expenses](screenshots/expenses.jpg)

### Recurring Expenses

![Recurring Expenses](screenshots/recurring-expenses.jpg)

## AI Recommendations Feature

The AI Recommendations feature leverages advanced generative AI models to provide personalized budget suggestions based on your historical spending data. This feature includes:

-   **Historical Data Analysis**: Analyzes your spending patterns over the last three months to identify trends and calculate averages, minimums, and maximums.
-   **Budget Suggestions**: Generates a recommended budget amount, along with a safe minimum and comfortable maximum, tailored to your spending habits.
-   **Actionable Insights**: Provides a brief explanation of the recommendation and a practical tip to help you stay within your budget.
-   **Real-Time Updates**: Automatically updates recommendations when you modify categories, months, or years.

### How It Works

1. **Data Collection**: The system fetches your historical spending data for the selected category and time period.
2. **AI Processing**: The data is sent to the Gemini AI model, which generates a JSON response with budget recommendations.
3. **User Display**: The recommendations are parsed and displayed in an easy-to-understand format.

### Key Benefits

-   Helps you make informed financial decisions.
-   Provides actionable tips to improve your budgeting habits.
-   Saves time by automating the budgeting process.
