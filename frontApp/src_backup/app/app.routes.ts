import { Routes } from '@angular/router';
import { Login } from './features/auth/login/login';
import { authGuard } from './core/guards/auth-guard';
import { Layout } from './layout/layout/layout';

export const routes: Routes = [
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: 'login', component: Login },

  {
    path: '',
    component: Layout,
    canActivate: [authGuard],
    children: [
      {
        path: 'dashboard',
        loadComponent: () => import('./features/dashboard/dashboard/dashboard').then(m => m.DashboardComponent)
      },
      {
        path: 'campus',
        loadComponent: () => import('./features/campus/campus/campus').then(m => m.CampusManagement)
      },
      {
        path: 'users',
        loadComponent: () => import('./features/users/users/users').then(m => m.Users)
      },
      {
        path: 'academic-years',
        loadComponent: () => import('./features/academic-years/academic-years/academic-years').then(m => m.AcademicYears)
      },
      {
        path: 'formations',
        loadComponent: () => import('./features/formations/formations/formations').then(m => m.Formations)
      },
      {
        path: 'logs',
        loadComponent: () => import('./features/logs/logs/logs').then(m => m.Logs)
      },
      {
        path: 'registrations',
        loadComponent: () => import('./features/registrations/registrations/registrations').then(m => m.Registrations)
      },
      {
        path: 'payments',
        loadComponent: () => import('./features/payments/payments/payments').then(m => m.PaymentsComponent)
      },
      {
        path: 'students',
        loadComponent: () => import('./features/students/students/students').then(m => m.StudentsComponent)
      },

      // ✅ NOUVELLE ROUTE : Caisse (remplace "expenses")
      {
        path: 'cash-movements',
        loadComponent: () => import('./features/cash-movements/cash-movements').then(m => m.CashMovementsComponent)
      },

      {
        path: 'financial-movements',
        loadComponent: () => import('./features/financial-movements/financial-movements/financial-movements').then(m => m.FinancialMovementsComponent)
      },
      {
        path: 'attestations',
        loadComponent: () => import('./features/attestations/attestations/attestations').then(m => m.AttestationsComponent)
      },
    ]
  }
];