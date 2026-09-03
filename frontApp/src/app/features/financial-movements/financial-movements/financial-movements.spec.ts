import { ComponentFixture, TestBed } from '@angular/core/testing';
import { FinancialMovements } from './financial-movements';

describe('FinancialMovements', () => {
  let component: FinancialMovements;
  let fixture: ComponentFixture<FinancialMovements>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FinancialMovements],
    }).compileComponents();

    fixture = TestBed.createComponent(FinancialMovements);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
