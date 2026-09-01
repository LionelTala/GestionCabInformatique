import { Routes } from '@angular/router';
import { Login } from './features/auth/login/login';
import { authGuard } from './core/guards/auth-guard';
import { Dashboard } from './features/dashboard/dashboard/dashboard';
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
        loadComponent: () => import('./features/dashboard/dashboard/dashboard').then(m => m.Dashboard)
      },
        { path: 'campus', loadComponent: () => import('./features/campus/campus/campus').then(m => m.CampusManagement) },   
        { path: 'users', loadComponent: () => import('./features/users/users/users').then(m => m.Users) },   
    ]
  }
];