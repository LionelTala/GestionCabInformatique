import { Routes } from '@angular/router';
import { Login } from './features/auth/login/login';
import { authGuard } from './core/guards/auth-guard';
import { Dashboard } from './features/dashboard/dashboard/dashboard';
import { Layout } from './layout/layout/layout';
import {DocumentVerificationComponent} from './pages/document-verification/document-verification';
 
export const routes: Routes = [
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: 'login', component: Login },
   {
    path: 'verify-document',
    component: DocumentVerificationComponent,
    title: 'Vérification de document - CAB Informatique'
  },

   {
    path: '',
    component: Layout,
    canActivate: [authGuard],
    children: [
      { 
        path: 'dashboard', 
        loadComponent: () => import('./features/dashboard/dashboard/dashboard').then(m => m.Dashboard)
      },
        { path: 'campus', loadComponent: () => import('./features/campus/campus/campus').then(m => m.CampusManagement) },   
        { path: 'users', loadComponent: () => import('./features/users/users/users').then(m => m.Users) },   
        { path: 'academic-years', loadComponent: () => import('./features/academic-years/academic-years/academic-years').then(m => m.AcademicYears) },
        { path: 'formations', loadComponent: () => import('./features/formations/formations/formations').then(m => m.Formations) },
        { path: 'logs', loadComponent: () => import('./features/logs/logs/logs').then(m => m.Logs) },       
         { path: 'registrations', loadComponent: () => import('./features/registrations/registrations/registrations').then(m => m.Registrations) },
         { 
        path: 'payments', 
        loadComponent: () => import('./features/payments/payments/payments').then(m => m.PaymentsComponent) 
      },
      { path: 'students', loadComponent: () => import('./features/students/students/students').then(m => m.StudentsComponent) },
      { path: 'expenses', loadComponent: () => import('./features/expenses/expenses/expenses').then(m => m.ExpensesComponent) },
      { path: 'financial-movements', loadComponent: () => import('./features/financial-movements/financial-movements/financial-movements').then(m => m.FinancialMovementsComponent) },
     ]
  }
];